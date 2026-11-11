import { Component, OnInit, ChangeDetectorRef, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import {
  CashMovement,
  CashMovementType,
  CashRegister,
  CashRegisterService,
  CashRegisterSession,
  ResponsibleUser,
} from '../services/cash-register';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PermissionService } from '../services/permission';
import { NotificationService } from '../services/notification';
import { PaginationMeta } from '../services/pagination';
import { HasPermission } from '../directives/has-permission';
import { APP_PIPES } from '../pipes';
import { tr } from '../services/i18n';

const EMPTY_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };

@Component({
  imports: [CommonModule, FormsModule, Layout, HasPermission, ...APP_PIPES],
  selector: 'app-cash-registers',
  styleUrl: './cash-registers.css',
  templateUrl: './cash-registers.html',
})
export class CashRegisters implements OnInit {
  registers: CashRegister[] = [];
  meta: PaginationMeta = { ...EMPTY_META };
  sessions: CashRegisterSession[] = [];
  sessionsMeta: PaginationMeta = { ...EMPTY_META };
  currentRegister: CashRegister | null = null;
  responsibleUsers: ResponsibleUser[] = [];
  isLoading = false;
  isSaving = false;
  errorMessage = '';
  formError = '';
  user: any;

  /** Register being opened, closed or receiving a movement in the active modal. */
  target: CashRegister | null = null;
  activeModal: 'create' | 'open' | 'close' | 'movement' | 'movements' | null = null;

  createForm = { name: '', location: '', user_id: null as number | null };
  openForm = { opening_balance: null as number | null, user_id: null as number | null };
  actualClosing: number | null = null;
  movementForm = { type: 'withdrawal' as CashMovementType, amount: null as number | null, reason: '' };

  movementsSession: CashRegisterSession | null = null;
  sessionMovements: CashMovement[] = [];
  isLoadingMovements = false;

  readonly movementTypes: { value: CashMovementType; label: string }[] = [
    { value: 'income', label: 'Ingreso' },
    { value: 'withdrawal', label: 'Retiro' },
    { value: 'expense', label: 'Egreso / Gasto' },
  ];

  constructor(
    private cashRegisterService: CashRegisterService,
    private authService: AuthService,
    private permissionService: PermissionService,
    private notificationService: NotificationService,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.refresh();
  }

  /** Re-sync when the cashier returns to the tab, e.g. after a manager opened their drawer. */
  @HostListener('window:focus')
  onWindowFocus(): void {
    if (!this.activeModal) {
      this.refresh();
    }
  }

  get isSupervisor(): boolean {
    return !(
      this.permissionService.hasRole('cashier') &&
      !this.permissionService.hasRole('admin') &&
      !this.permissionService.hasRole('manager')
    );
  }

  get currentSession(): CashRegisterSession | null {
    return this.currentRegister?.current_session ?? null;
  }

  get targetSession(): CashRegisterSession | null {
    return this.target?.current_session ?? null;
  }

  refresh(): void {
    this.loadCurrent();
    this.loadRegisters(this.meta.current_page);
    this.loadSessions(this.sessionsMeta.current_page);
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
        this.errorMessage = error.error?.message || 'Error al cargar cajas registradoras';
        this.isLoading = false;
        this.registers = [];
        this.cdr.detectChanges();
      },
    });
  }

  loadSessions(page = 1): void {
    this.cashRegisterService.getSessions({ page, per_page: 10 }).subscribe({
      next: (response) => {
        this.sessions = response.data || [];
        this.sessionsMeta = response.meta;
        this.cdr.detectChanges();
      },
      error: () => {
        this.sessions = [];
        this.cdr.detectChanges();
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadRegisters(page);
    }
  }

  goToSessionsPage(page: number): void {
    if (page >= 1 && page <= this.sessionsMeta.last_page) {
      this.loadSessions(page);
    }
  }

  // ---------- Permisos por caja ----------

  private ownsRegister(register: CashRegister): boolean {
    return register.user_id === this.user?.id || register.current_session?.user_id === this.user?.id;
  }

  canOpen(register: CashRegister): boolean {
    return (
      register.status === 'closed' &&
      this.authService.hasPermission('cash-registers.open') &&
      (this.isSupervisor || register.user_id === this.user?.id)
    );
  }

  canClose(register: CashRegister): boolean {
    return (
      register.status === 'open' &&
      this.authService.hasPermission('cash-registers.close') &&
      (this.isSupervisor || register.current_session?.user_id === this.user?.id)
    );
  }

  canRecordMovement(register: CashRegister): boolean {
    return (
      register.status === 'open' &&
      this.authService.hasPermission('cash-registers.open') &&
      (this.isSupervisor || this.ownsRegister(register))
    );
  }

  // ---------- Modales ----------

  private showModal(modal: CashRegisters['activeModal'], register: CashRegister | null = null): void {
    this.target = register;
    this.formError = '';
    this.activeModal = modal;
    this.cdr.detectChanges();
  }

  closeModal(): void {
    this.activeModal = null;
    this.target = null;
    this.movementsSession = null;
    this.sessionMovements = [];
    this.formError = '';
  }

  startCreate(): void {
    this.createForm = { name: '', location: '', user_id: null };
    this.loadResponsibleUsers();
    this.showModal('create');
  }

  startOpen(register: CashRegister): void {
    this.openForm = {
      opening_balance: null,
      user_id: this.isSupervisor ? register.user_id ?? null : this.user?.id ?? null,
    };
    if (this.isSupervisor) {
      this.loadResponsibleUsers();
    }
    this.showModal('open', register);
  }

  startClose(register: CashRegister): void {
    this.actualClosing = null;
    this.showModal('close', register);
  }

  startMovement(register: CashRegister): void {
    this.movementForm = { type: 'withdrawal', amount: null, reason: '' };
    this.showModal('movement', register);
  }

  viewMovements(session: CashRegisterSession): void {
    this.movementsSession = session;
    this.sessionMovements = [];
    this.isLoadingMovements = true;
    this.showModal('movements');
    this.cashRegisterService.getSessionMovements(session.id).subscribe({
      next: (response) => {
        this.sessionMovements = response.movements;
        this.isLoadingMovements = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        this.formError = error.error?.message || 'Error al cargar los movimientos';
        this.isLoadingMovements = false;
        this.cdr.detectChanges();
      },
    });
  }

  private loadResponsibleUsers(): void {
    this.cashRegisterService.getResponsibleUsers().subscribe({
      next: (response) => {
        this.responsibleUsers = response.users;
        this.cdr.detectChanges();
      },
      error: () => {
        this.responsibleUsers = [];
      },
    });
  }

  // ---------- Acciones ----------

  createRegister(): void {
    if (!this.createForm.name.trim()) {
      this.formError = 'El nombre de la caja es obligatorio';
      return;
    }
    this.submit(
      this.cashRegisterService.createCashRegister({
        name: this.createForm.name.trim(),
        location: this.createForm.location.trim() || undefined,
        user_id: this.createForm.user_id,
      }),
      'Caja creada. Queda cerrada hasta que se abra un turno.',
      'Error al crear la caja'
    );
  }

  openRegister(): void {
    const balance = this.toNumber(this.openForm.opening_balance);
    if (!this.target) {
      return;
    }
    if (this.openForm.opening_balance === null || balance < 0) {
      this.formError = 'Ingresa un fondo inicial válido (0 o mayor)';
      return;
    }
    if (this.isSupervisor && !this.openForm.user_id) {
      this.formError = 'Selecciona el usuario responsable de la caja';
      return;
    }
    const responsible = this.isSupervisor ? this.openForm.user_id : null;
    this.submit(
      this.cashRegisterService.openCashRegister(this.target.id, balance, responsible),
      'Caja abierta correctamente',
      'Error al abrir la caja'
    );
  }

  get closingDifference(): number | null {
    if (this.actualClosing === null || !this.targetSession) {
      return null;
    }
    return Math.round((this.toNumber(this.actualClosing) - this.toNumber(this.targetSession.expected_closing)) * 100) / 100;
  }

  /** Net effect of manual movements: expected minus fund and cash sales. */
  get targetMovementsNet(): number {
    const s = this.targetSession;
    if (!s) {
      return 0;
    }
    return this.toNumber(s.expected_closing) - this.toNumber(s.opening_balance) - this.toNumber(s.cash_sales);
  }

  get isSupervisedClose(): boolean {
    return !!this.targetSession && this.targetSession.user_id !== this.user?.id;
  }

  closeRegister(): void {
    if (!this.target) {
      return;
    }
    if (this.actualClosing === null || this.toNumber(this.actualClosing) < 0) {
      this.formError = 'Ingresa el efectivo contado (0 o mayor)';
      return;
    }
    const difference = this.closingDifference ?? 0;
    const message =
      difference === 0
        ? 'Caja cerrada. Arqueo cuadrado.'
        : difference < 0
          ? 'Caja cerrada con faltante registrado.'
          : 'Caja cerrada con sobrante registrado.';
    this.submit(
      this.cashRegisterService.closeCashRegister(this.target.id, this.toNumber(this.actualClosing)),
      message,
      'Error al cerrar la caja'
    );
  }

  recordMovement(): void {
    if (!this.target) {
      return;
    }
    if (this.toNumber(this.movementForm.amount) <= 0) {
      this.formError = 'El monto debe ser mayor que 0';
      return;
    }
    if (!this.movementForm.reason.trim()) {
      this.formError = 'Indica el motivo del movimiento';
      return;
    }
    if (
      this.movementForm.type !== 'income' &&
      this.toNumber(this.movementForm.amount) > this.toNumber(this.targetSession?.expected_closing)
    ) {
      this.formError = 'El monto supera el efectivo esperado en caja';
      return;
    }
    this.submit(
      this.cashRegisterService.createMovement({
        type: this.movementForm.type,
        amount: this.toNumber(this.movementForm.amount),
        reason: this.movementForm.reason.trim(),
        cash_register_id: this.target.id,
      }),
      'Movimiento registrado',
      'Error al registrar el movimiento'
    );
  }

  private submit(request: Observable<unknown>, successMessage: string, errorMessage: string): void {
    this.isSaving = true;
    this.formError = '';
    request.subscribe({
      next: () => {
        this.isSaving = false;
        this.closeModal();
        this.notificationService.success(tr(successMessage));
        this.refresh();
      },
      error: (error) => {
        this.isSaving = false;
        this.formError = this.extractError(error) || errorMessage;
        // Another user may have changed the drawer meanwhile; resync the state.
        this.refresh();
        this.cdr.detectChanges();
      },
    });
  }

  private extractError(error: any): string {
    const errors = error?.error?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      return Array.isArray(first) ? String(first[0]) : String(first);
    }
    return error?.error?.message || '';
  }

  isResponsibleListed(userId: number | undefined): boolean {
    return this.responsibleUsers.some((u) => u.id === userId);
  }

  movementLabel(type: string): string {
    return this.movementTypes.find((t) => t.value === type)?.label ?? type;
  }

  toNumber(value: number | string | null | undefined): number {
    const n = typeof value === 'string' ? parseFloat(value) : value;
    return n === null || n === undefined || isNaN(n) ? 0 : n;
  }
}
