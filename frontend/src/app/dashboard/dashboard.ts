import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { AuthService } from '../services/auth';
import { PermissionService } from '../services/permission';
import { DashboardService } from '../services/dashboard';
import { Layout } from '../layout/layout';

@Component({
  imports: [CommonModule, Layout],
  selector: 'app-dashboard',
  styleUrl: './dashboard.css',
  templateUrl: './dashboard.html',
})
export class Dashboard implements OnInit, OnDestroy {
  user: any;
  totalSalesToday: number = 0;
  totalProducts: number = 0;
  lowStockProducts: number = 0;
  activeUsers: number = 0;
  salesLast7Days: Array<{ date: string; total: number }> = [];
  topCategories: Array<{ name: string; total_sold: number }> = [];
  topProducts: Array<{ name: string; total_quantity: number; total_revenue: number }> = [];
  salesByPayment: Array<{ payment_method: string; total_transactions: number; total_revenue: number }> = [];
  loading: boolean = true;

  constructor(
    private authService: AuthService,
    private permissionService: PermissionService,
    private dashboardService: DashboardService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
    this.permissionService.refreshPermissions();
  }

  ngOnInit(): void {
    this.loadDashboardData();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/dashboard' || event.urlAfterRedirects === '/dashboard') {
        this.loadDashboardData();
      }
    });
  }

  ngOnDestroy(): void {
    // Cleanup if needed
  }

  loadDashboardData(): void {
    this.loading = true;

    this.dashboardService.getDashboardData().subscribe({
      next: (data) => {
        this.totalSalesToday = data.total_sales_today;
        this.totalProducts = data.total_products;
        this.lowStockProducts = data.low_stock_products;
        this.activeUsers = data.active_users;
        this.salesLast7Days = data.sales_last_7_days;
        this.topCategories = data.top_categories;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading dashboard data:', error);
        this.loading = false;
        this.cdr.detectChanges();
      },
    });

    this.dashboardService.getTopProducts().subscribe({
      next: (response) => {
        this.topProducts = (response.top_products || []).map((item) => ({
          name: item.product?.name || 'Sin nombre',
          total_quantity: item.total_quantity,
          total_revenue: item.total_revenue,
        }));
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading top products:', error),
    });

    this.dashboardService.getSalesByPaymentMethod().subscribe({
      next: (response) => {
        this.salesByPayment = response.sales_by_payment_method || [];
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading sales by payment:', error),
    });
  }

  get canViewUsers(): boolean {
    return this.permissionService.hasPermission('users.view');
  }

  get canViewCategories(): boolean {
    return this.permissionService.hasPermission('categories.view');
  }

  get canViewProducts(): boolean {
    return this.permissionService.hasPermission('products.view');
  }

  get canViewPos(): boolean {
    return this.permissionService.hasPermission('sales.create');
  }

  get canViewInventory(): boolean {
    return this.permissionService.hasPermission('inventory.view');
  }

  get canViewReports(): boolean {
    return this.permissionService.hasPermission('reports.view');
  }

  get canViewSuppliers(): boolean {
    return this.permissionService.hasPermission('suppliers.view');
  }

  get canViewReturns(): boolean {
    return this.permissionService.hasPermission('returns.view');
  }

  get canViewDiscounts(): boolean {
    return this.permissionService.hasPermission('discounts.view');
  }

  get canViewCashRegisters(): boolean {
    return this.permissionService.hasPermission('cash-registers.view');
  }

  get canViewSettings(): boolean {
    return this.permissionService.hasPermission('settings.view');
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/login']);
      },
      error: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        this.router.navigate(['/login']);
      },
    });
  }

  getMaxSales(): number {
    if (this.salesLast7Days.length === 0) return 0;
    return Math.max(...this.salesLast7Days.map((sale) => sale.total));
  }

  getMaxProductQuantity(): number {
    if (this.topProducts.length === 0) return 0;
    return Math.max(...this.topProducts.map((p) => p.total_quantity));
  }

  get paymentTotal(): number {
    return this.salesByPayment.reduce((sum, s) => sum + s.total_revenue, 0);
  }

  get paymentDonutGradient(): string {
    const colors: Record<string, string> = {
      cash: '#059669',
      card: '#2563eb',
      transfer: '#d97706',
    };
    const total = this.paymentTotal;
    if (total <= 0) return 'conic-gradient(#e5e7eb 0% 100%)';

    let start = 0;
    const stops = this.salesByPayment.map((s) => {
      const pct = (s.total_revenue / total) * 100;
      const color = colors[s.payment_method] || '#6b7280';
      const stop = `${color} ${start}% ${start + pct}%`;
      start += pct;
      return stop;
    });
    return `conic-gradient(${stops.join(', ')})`;
  }

  getPaymentLabel(method: string): string {
    const labels: Record<string, string> = {
      cash: 'Efectivo',
      card: 'Tarjeta',
      transfer: 'Transferencia',
    };
    return labels[method] || method;
  }

  getPaymentColor(method: string): string {
    const colors: Record<string, string> = {
      cash: '#059669',
      card: '#2563eb',
      transfer: '#d97706',
    };
    return colors[method] || '#6b7280';
  }
}