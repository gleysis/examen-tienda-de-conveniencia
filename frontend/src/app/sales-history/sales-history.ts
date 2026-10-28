import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { SaleService } from '../services/sale';
import type { Sale } from '../services/sale';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PaginationMeta } from '../services/pagination';

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-sales-history',
  styleUrl: './sales-history.css',
  templateUrl: './sales-history.html',
})
export class SalesHistory implements OnInit, OnDestroy {
  sales: Sale[] = [];
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = false;
  errorMessage = '';
  user: any;
  selectedSale: Sale | null = null;
  showDetails = false;

  searchQuery = '';
  dateFilter = {
    date_from: '',
    date_to: '',
  };

  constructor(
    private saleService: SaleService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadSales();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/sales-history' || event.urlAfterRedirects === '/sales-history') {
        this.loadSales();
      }
    });
  }

  ngOnDestroy(): void {
    // Cleanup if needed
  }

  loadSales(page = 1): void {
    this.isLoading = true;
    const params: any = { page };
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;
    if (this.searchQuery) params.search = this.searchQuery;

    this.saleService.getSales(params).subscribe({
      next: (response) => {
        this.sales = response.data;
        this.meta = response.meta;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading sales:', error);
        this.errorMessage = 'Error al cargar el historial de ventas';
        this.isLoading = false;
        this.cdr.detectChanges();
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadSales(page);
    }
  }

  onSearch(): void {
    this.loadSales();
  }

  onFilter(): void {
    this.loadSales();
  }

  viewDetails(sale: Sale): void {
    this.selectedSale = sale;
    this.showDetails = true;
    this.cdr.detectChanges();
  }

  closeDetails(): void {
    this.showDetails = false;
    this.selectedSale = null;
    this.cdr.detectChanges();
  }

  printReceipt(sale: Sale): void {
    alert('Comprobante de venta:\n\nFactura: ' + sale.invoice_number + '\nTotal: $' + sale.total.toFixed(2) + '\nMétodo de pago: ' + this.getPaymentMethodLabel(sale.payment_method) + '\nFecha: ' + new Date(sale.created_at).toLocaleString());
  }

  getPaymentMethodLabel(method: string): string {
    const labels: any = {
      cash: 'Efectivo',
      card: 'Tarjeta',
      transfer: 'Transferencia',
    };
    return labels[method] || method;
  }

  getStatusLabel(status: string): string {
    const labels: any = {
      pending: 'Pendiente',
      completed: 'Completada',
      cancelled: 'Cancelada',
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: any = {
      pending: 'status-pending',
      completed: 'status-completed',
      cancelled: 'status-cancelled',
    };
    return classes[status] || '';
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {
        window.location.href = '/login';
      },
      error: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.location.href = '/login';
      },
    });
  }
}
