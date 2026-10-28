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

  get canViewCustomers(): boolean {
    return this.permissionService.hasPermission('customers.view');
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
}