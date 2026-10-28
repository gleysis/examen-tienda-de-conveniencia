import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CashRegisterService } from '../services/cash-register';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PaginationMeta } from '../services/pagination';

interface CashRegister {
  id: number;
  name: string;
  location: string | null;
  user_id: number | null;
  opening_balance: number;
  current_balance: number;
  cash_sales: number;
  card_sales: number;
  transfer_sales: number;
  total_sales: number;
  expected_closing: number;
  actual_closing: number | null;
  difference: number | null;
  status: string;
  opened_at: string | null;
  closed_at: string | null;
  notes: string | null;
  user?: { name?: string } | null;
}

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-cash-registers',
  styleUrl: './cash-registers.css',
  templateUrl: './cash-registers.html',
})
export class CashRegisters implements OnInit {
  registers: CashRegister[] = [];
  currentRegister: CashRegister | null = null;
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = false;
  errorMessage = '';
  user: any;

  showOpenForm = false;
  showCloseForm = false;

  openForm = {
    name: '',
    location: '',
    opening_balance: 0,
  };

  actualClosing: number | null = null;

  constructor(
    private cashRegisterService: CashRegisterService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadCurrent();
    this.loadRegisters();
  }

  loadCurrent(): void {
    this.cashRegisterService.getCurrentCashRegister().subscribe({
      next: (response) => {
        this.currentRegister = response.cash_register;
        this.cdr.detectChanges();
      },
      error: () => {
        this.currentRegister = null;
        this.cdr.detectChanges();
      },
    });
  }

  loadRegisters(page = 1): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.cashRegisterService.getCashRegisters({ page }).subscribe({
      next: (response) => {
        this.registers = response.data || [];
        this.meta = response.meta;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading cash registers:', error);
        this.errorMessage = error.error?.message || 'Error al cargar cajas registradoras';
        this.isLoading = false;
        this.registers = [];
        this.cdr.detectChanges();
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadRegisters(page);
    }
  }

  showAddForm(): void {
    this.openForm = { name: '', location: '', opening_balance: 0 };
    this.showOpenForm = true;
  }

  cancelOpenForm(): void {
    this.showOpenForm = false;
  }

  openRegister(): void {
    this.errorMessage = '';
    this.cashRegisterService.createCashRegister(this.openForm).subscribe({
      next: () => {
        this.showOpenForm = false;
        this.loadCurrent();
        this.loadRegisters();
      },
      error: (error) => {
        console.error('Error opening register:', error);
        this.errorMessage = error.error?.message || 'Error al abrir la caja';
        this.cdr.detectChanges();
      },
    });
  }

  startClose(): void {
    this.actualClosing = null;
    this.showCloseForm = true;
  }

  cancelClose(): void {
    this.showCloseForm = false;
  }

  get closingDifference(): number | null {
    if (this.actualClosing === null || !this.currentRegister) {
      return null;
    }
    return this.actualClosing - this.currentRegister.expected_closing;
  }

  closeRegister(): void {
    if (!this.currentRegister || this.actualClosing === null) {
      return;
    }

    this.errorMessage = '';
    this.cashRegisterService
      .closeCashRegister(this.currentRegister.id, this.actualClosing)
      .subscribe({
        next: () => {
          this.showCloseForm = false;
          this.currentRegister = null;
          this.loadRegisters();
        },
        error: (error) => {
          console.error('Error closing register:', error);
          this.errorMessage = error.error?.message || 'Error al cerrar la caja';
          this.cdr.detectChanges();
        },
      });
  }

  reopenRegister(register: CashRegister): void {
    const balance = register.actual_closing ?? register.expected_closing ?? 0;
    const input = prompt(
      `Balance de apertura para "${register.name}"`,
      balance.toString()
    );
    if (input === null) {
      return;
    }

    const opening = parseFloat(input);
    if (isNaN(opening) || opening < 0) {
      this.errorMessage = 'El balance de apertura debe ser un número válido';
      return;
    }

    this.errorMessage = '';
    this.cashRegisterService.openCashRegister(register.id, opening).subscribe({
      next: () => {
        this.loadCurrent();
        this.loadRegisters();
      },
      error: (error) => {
        console.error('Error reopening register:', error);
        this.errorMessage = error.error?.message || 'Error al reabrir la caja';
        this.cdr.detectChanges();
      },
    });
  }

  toNumber(value: number | string | null): number {
    const n = typeof value === 'string' ? parseFloat(value) : value;
    return n === null || isNaN(n) ? 0 : n;
  }
}
