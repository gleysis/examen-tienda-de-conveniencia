import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { SaleService } from '../services/sale';
import { InventoryService } from '../services/inventory';
import { ReturnRefundService } from '../services/return-refund';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';

import type { Sale } from '../services/sale';

interface ReturnRefund {
  id: number;
  return_number: string;
  refund_amount: number;
  status: string;
  created_at?: string;
}

@Component({
  imports: [CommonModule, FormsModule, Layout, DatePipe],
  selector: 'app-reports',
  styleUrl: './reports.css',
  templateUrl: './reports.html',
})
export class Reports implements OnInit, OnDestroy {
  sales: Sale[] = [];
  returns: ReturnRefund[] = [];
  isLoading = false;
  errorMessage = '';
  activeTab = 'sales';
  user: any;

  salesSummary: any = null;
  inventorySummary: any = null;
  returnsSummary: any = null;
  topProductsData: any = null;
  paymentMethodsData: any = null;

  dateFilter = {
    date_from: '',
    date_to: '',
  };

  constructor(
    private saleService: SaleService,
    private inventoryService: InventoryService,
    private returnRefundService: ReturnRefundService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router,
    private http: HttpClient
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    console.log('Reports ngOnInit called');
    
    // Set default dates to today
    const today = new Date();
    const dateFrom = today.toISOString().split('T')[0];
    const dateTo = today.toISOString().split('T')[0];
    
    this.dateFilter = {
      date_from: dateFrom,
      date_to: dateTo,
    };
    
    this.loadSales();
    this.loadInventoryReport();
    this.loadLowStock();
    this.loadReturns();
    this.loadTopProducts();
    this.loadPaymentMethods();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/reports' || event.urlAfterRedirects === '/reports') {
        console.log('Navigated to reports, reloading data');
        this.loadSales();
        this.loadInventoryReport();
        this.loadLowStock();
        this.loadReturns();
        this.loadTopProducts();
        this.loadPaymentMethods();
      }
    });
  }

  ngOnDestroy(): void {
    console.log('Reports ngOnDestroy called');
  }

  loadSales(): void {
    console.log('Loading sales...');
    this.isLoading = true;
    const params: any = { per_page: 100 };
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;

    this.saleService.getSales(params).subscribe({
      next: (response) => {
        console.log('Sales loaded:', response);
        this.sales = response.data || [];
        this.calculateSalesSummary();
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading sales:', error);
        this.errorMessage = 'Error al cargar ventas';
        this.isLoading = false;
        this.cdr.detectChanges();
      },
    });
  }

  loadInventoryReport(): void {
    console.log('Loading inventory report...');
    this.inventoryService.getReport().subscribe({
      next: (response) => {
        console.log('Inventory report loaded:', response);
        this.inventorySummary = response.summary;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading inventory report:', error);
        this.cdr.detectChanges();
      },
    });
  }

  loadLowStock(): void {
    console.log('Loading low stock products...');
    this.inventoryService.getLowStock().subscribe({
      next: (response) => {
        console.log('Low stock loaded:', response);
        if (this.inventorySummary) {
          this.inventorySummary.low_stock_products = response.products;
        }
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading low stock:', error);
        this.cdr.detectChanges();
      },
    });
  }

  loadReturns(): void {
    console.log('Loading returns...');
    const params: any = { per_page: 100 };
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;

    this.returnRefundService.getReturns(params).subscribe({
      next: (response) => {
        console.log('Returns loaded:', response);
        this.returns = response.data || [];
        this.calculateReturnsSummary();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading returns:', error);
        this.cdr.detectChanges();
      },
    });
  }

  calculateSalesSummary(): void {
    const totalSales = this.sales.length;
    const totalRevenue = this.sales.reduce((sum, sale) => sum + sale.total, 0);
    const completedSales = this.sales.filter((s) => s.status === 'completed').length;
    const avgSaleValue = totalSales > 0 ? totalRevenue / totalSales : 0;

    const paymentMethods = this.sales.reduce((acc, sale) => {
      acc[sale.payment_method] = (acc[sale.payment_method] || 0) + 1;
      return acc;
    }, {} as any);

    this.salesSummary = {
      total_sales: totalSales,
      total_revenue: totalRevenue,
      completed_sales: completedSales,
      avg_sale_value: avgSaleValue,
      payment_methods: paymentMethods,
    };
  }

  calculateReturnsSummary(): void {
    const totalReturns = this.returns.length;
    const totalRefunded = this.returns.reduce((sum, ret) => sum + ret.refund_amount, 0);
    const processedReturns = this.returns.filter((r) => r.status === 'processed').length;
    const pendingReturns = this.returns.filter((r) => r.status === 'pending').length;

    this.returnsSummary = {
      total_returns: totalReturns,
      total_refunded: totalRefunded,
      processed_returns: processedReturns,
      pending_returns: pendingReturns,
    };
  }

  loadTopProducts(): void {
    console.log('Loading top products...');
    const params: any = {};
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;

    this.http.get('http://127.0.0.1:8000/api/dashboard/top-products', { params }).subscribe({
      next: (response: any) => {
        console.log('Top products loaded:', response);
        this.topProductsData = response;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading top products:', error);
        this.cdr.detectChanges();
      },
    });
  }

  loadPaymentMethods(): void {
    console.log('Loading payment methods...');
    const params: any = {};
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;

    this.http.get('http://127.0.0.1:8000/api/dashboard/sales-by-payment', { params }).subscribe({
      next: (response: any) => {
        console.log('Payment methods loaded:', response);
        this.paymentMethodsData = response;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading payment methods:', error);
        this.cdr.detectChanges();
      },
    });
  }

  getTotalQuantitySold(): number {
    if (!this.topProductsData?.top_products) return 0;
    return this.topProductsData.top_products.reduce((sum: number, item: any) => sum + item.total_quantity, 0);
  }

  getTotalRevenue(): number {
    if (!this.topProductsData?.top_products) return 0;
    return this.topProductsData.top_products.reduce((sum: number, item: any) => sum + item.total_revenue, 0);
  }

  getTotalTransactions(): number {
    if (!this.paymentMethodsData?.sales_by_payment_method) return 0;
    return this.paymentMethodsData.sales_by_payment_method.reduce((sum: number, item: any) => sum + item.total_transactions, 0);
  }

  getTotalPaymentRevenue(): number {
    if (!this.paymentMethodsData?.sales_by_payment_method) return 0;
    return this.paymentMethodsData.sales_by_payment_method.reduce((sum: number, item: any) => sum + item.total_revenue, 0);
  }

  getPaymentMethodLabel(method: string): string {
    const labels: any = {
      cash: 'Efectivo',
      card: 'Tarjeta',
      transfer: 'Transferencia',
    };
    return labels[method] || method;
  }

  onFilter(): void {
    this.loadSales();
    this.loadReturns();
  }

  setTab(tab: string): void {
    this.activeTab = tab;
  }

  getStatusLabel(status: string): string {
    const labels: any = {
      pending: 'Pendiente',
      approved: 'Aprobado',
      rejected: 'Rechazado',
      processed: 'Procesado',
    };
    return labels[status] || status;
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
