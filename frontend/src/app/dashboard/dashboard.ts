import { Component, OnInit, ChangeDetectorRef, OnDestroy, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd, RouterModule } from '@angular/router';
import { filter } from 'rxjs/operators';
import { AuthService } from '../services/auth';
import { PermissionService } from '../services/permission';
import {
  DashboardService,
  DashboardCards,
  DashboardAlerts,
  InventoryStatusResponse,
  MyRegister,
  PaymentMethodItem,
  SalesByHourResponse,
  SalesChartResponse,
  TopProductItem,
  CashierSale,
} from '../services/dashboard';
import { Layout } from '../layout/layout';
import { DateInput } from '../directives/date-input';
import { APP_PIPES } from '../pipes';
import { MoneyPipe } from '../pipes/money';
import { PreferencesService } from '../services/preferences';

type PeriodKey = 'today' | '7d' | '30d' | 'custom';

@Component({
  imports: [CommonModule, FormsModule, DateInput, RouterModule, Layout, ...APP_PIPES],
  selector: 'app-dashboard',
  styleUrl: './dashboard.css',
  templateUrl: './dashboard.html',
})
export class Dashboard implements OnInit, OnDestroy {
  user: any;

  cards: DashboardCards | null = null;
  myRegister: MyRegister | null = null;
  scope: 'own' | 'all' = 'all';

  period: PeriodKey = '30d';
  customFrom = '';
  customTo = '';
  compareEnabled = false;

  salesChart: SalesChartResponse | null = null;
  paymentMethods: PaymentMethodItem[] = [];
  paymentTotal = 0;
  topProducts: TopProductItem[] = [];
  topMetric: 'quantity' | 'revenue' = 'quantity';
  topLimit: 5 | 10 = 5;
  inventory: InventoryStatusResponse | null = null;
  cashiers: CashierSale[] = [];
  cashiersScoped = false;
  hoursData: SalesByHourResponse | null = null;
  alerts: DashboardAlerts | null = null;

  loading = true;
  chartLoading = false;
  errorMessage = '';

  readonly chartW = 660;
  readonly chartH = 250;
  readonly padL = 52;
  readonly padR = 8;
  readonly padT = 12;
  readonly padB = 26;

  readonly ringRadii = [70, 56, 42, 28];
  readonly gaugePath = 'M 20 100 A 80 80 0 0 1 180 100';

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

  get isCashierOnly(): boolean {
    return (
      this.permissionService.hasRole('cashier') &&
      !this.permissionService.hasRole('admin') &&
      !this.permissionService.hasRole('manager')
    );
  }

  get canViewPos(): boolean {
    return this.permissionService.hasPermission('sales.create');
  }

  get canViewInventory(): boolean {
    return this.permissionService.hasAnyPermission(['products.view', 'inventory.view', 'inventory.low-stock']);
  }

  get canViewCashRegisters(): boolean {
    return this.permissionService.hasPermission('cash-registers.view');
  }

  get canViewReports(): boolean {
    return this.permissionService.hasPermission('reports.view');
  }

  get showCashierChart(): boolean {
    return !this.isCashierOnly && this.cashiers.length > 0;
  }

  get periodRange(): { date_from: string; date_to: string } {
    const today = new Date();
    const date_to = this.toISODate(today);
    if (this.period === 'today') {
      return { date_from: date_to, date_to };
    }
    if (this.period === 'custom' && this.customFrom && this.customTo) {
      return { date_from: this.customFrom, date_to: this.customTo };
    }
    const days = this.period === '30d' ? 30 : 7;
    const from = new Date(today);
    from.setDate(from.getDate() - (days - 1));
    return { date_from: this.toISODate(from), date_to };
  }

  private toISODate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  setPeriod(period: PeriodKey): void {
    this.period = period;
    if (period !== 'custom') {
      this.loadDashboardData();
    }
  }

  applyCustomRange(): void {
    if (this.customFrom && this.customTo) {
      this.loadDashboardData();
    }
  }

  toggleCompare(): void {
    this.compareEnabled = !this.compareEnabled;
    this.loadSalesChart();
  }

  setTopMetric(metric: 'quantity' | 'revenue'): void {
    if (this.topMetric !== metric) {
      this.topMetric = metric;
      this.loadTopProducts();
    }
  }

  setTopLimit(limit: 5 | 10): void {
    if (this.topLimit !== limit) {
      this.topLimit = limit;
      this.loadTopProducts();
    }
  }

  loadDashboardData(): void {
    this.loading = true;
    this.errorMessage = '';
    const filters = this.periodRange;

    this.dashboardService.getDashboardData(filters).subscribe({
      next: (data) => {
        this.cards = data.cards;
        this.scope = data.scope;
        this.myRegister = data.my_register ?? null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading dashboard data:', error);
        this.errorMessage = 'No se pudieron cargar los datos del panel.';
        this.loading = false;
        this.cdr.detectChanges();
      },
    });

    this.loadSalesChart();
    this.loadTopProducts();
    this.loadPaymentMethods();
    this.loadInventoryStatus();
    this.loadSalesByCashier();
    this.loadSalesByHour();
    this.loadAlerts();
  }

  loadSalesChart(): void {
    this.chartLoading = true;
    this.dashboardService
      .getSalesChart({ ...this.periodRange, compare: this.compareEnabled })
      .subscribe({
        next: (data) => {
          this.salesChart = data;
          this.chartLoading = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error loading sales chart:', error);
          this.chartLoading = false;
          this.cdr.detectChanges();
        },
      });
  }

  loadTopProducts(): void {
    this.dashboardService
      .getTopProducts({ ...this.periodRange, metric: this.topMetric, limit: this.topLimit })
      .subscribe({
        next: (response) => {
          this.topProducts = response.top_products || [];
          this.cdr.detectChanges();
        },
        error: (error) => console.error('Error loading top products:', error),
      });
  }

  loadPaymentMethods(): void {
    this.dashboardService.getSalesByPaymentMethod(this.periodRange).subscribe({
      next: (response) => {
        this.paymentMethods = response.sales_by_payment_method || [];
        this.paymentTotal = response.total_revenue || 0;
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading sales by payment:', error),
    });
  }

  loadInventoryStatus(): void {
    this.dashboardService.getInventoryStatus().subscribe({
      next: (response) => {
        this.inventory = response;
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading inventory status:', error),
    });
  }

  loadSalesByCashier(): void {
    if (this.isCashierOnly) {
      this.cashiers = [];
      return;
    }
    this.dashboardService.getSalesByCashier(this.periodRange).subscribe({
      next: (response) => {
        this.cashiers = response.cashiers || [];
        this.cashiersScoped = response.scoped;
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading sales by cashier:', error),
    });
  }

  loadSalesByHour(): void {
    this.dashboardService.getSalesByHour(this.periodRange).subscribe({
      next: (response) => {
        this.hoursData = response;
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading sales by hour:', error),
    });
  }

  loadAlerts(): void {
    this.dashboardService.getAlerts().subscribe({
      next: (response) => {
        this.alerts = response;
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading alerts:', error),
    });
  }

  get alertCount(): number {
    if (!this.alerts) return 0;
    return (
      this.alerts.low_stock.length +
      this.alerts.out_of_stock.length +
      this.alerts.expiring_soon.length +
      this.alerts.expired.length +
      this.alerts.open_registers.length +
      this.alerts.discrepancies.length
    );
  }

  // ---------- Formato ----------

  private readonly moneyPipe = new MoneyPipe();
  private readonly preferences = inject(PreferencesService);

  formatMoney(value: number | null | undefined): string {
    return this.moneyPipe.transform(value);
  }

  formatCompact(value: number): string {
    if (value >= 1000) {
      return (value / 1000).toFixed(value >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'k';
    }
    return String(Math.round(value));
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const [, m, d] = dateStr.split('-');
    const format = this.preferences.dateFormat;
    if (format.startsWith('MM')) return `${m}/${d}`;
    if (format.startsWith('yyyy')) return `${m}-${d}`;
    return `${d}/${m}`;
  }

  formatHour(hour: number): string {
    if (this.preferences.timeFormat === 'HH:mm') {
      return `${String(hour).padStart(2, '0')}h`;
    }
    const period = hour >= 12 ? 'PM' : 'AM';
    const h = hour % 12 || 12;
    return `${h}${period}`;
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
      cash: '#E91E83',
      card: '#6B21A8',
      transfer: '#FFC928',
    };
    return colors[method] || '#756B80';
  }

  // ---------- Gráfica de barras (ventas por día) ----------

  get series(): { date: string; total: number; invoices: number }[] {
    return this.salesChart?.series ?? [];
  }

  get maxChartValue(): number {
    let max = 0;
    for (const p of this.series) max = Math.max(max, p.total);
    if (this.salesChart?.previous) {
      for (const p of this.salesChart.previous.series) max = Math.max(max, p.total);
    }
    return max > 0 ? max * 1.1 : 1;
  }

  private slotCenter(index: number): number {
    const innerW = this.chartW - this.padL - this.padR;
    const slot = innerW / Math.max(1, this.series.length);
    return this.padL + slot * index + slot / 2;
  }

  private pointY(value: number): number {
    const innerH = this.chartH - this.padT - this.padB;
    return this.padT + innerH - (value / this.maxChartValue) * innerH;
  }

  /**
   * Vertical bars for the daily sales chart, optionally paired with the previous period.
   *
   * @return array<{ x: number, y: number, w: number, h: number, p: object, prev: { x: number, y: number, h: number, total: number } | null }>
   */
  get bars(): {
    x: number;
    y: number;
    w: number;
    h: number;
    p: { date: string; total: number; invoices: number };
    prev: { x: number; y: number; h: number; total: number } | null;
  }[] {
    const n = this.series.length;
    if (n === 0) return [];
    const innerW = this.chartW - this.padL - this.padR;
    const previous = this.salesChart?.previous?.series ?? [];
    const hasPrevious = previous.length > 0;
    const group = Math.min((innerW / n) * 0.62, hasPrevious ? 30 : 18);
    const w = hasPrevious ? group / 2 - 1 : group;
    const bottom = this.chartBottom;

    return this.series.map((p, i) => {
      const center = this.slotCenter(i);
      const y = this.pointY(p.total);
      const prevPoint = previous[i];
      const prevY = prevPoint ? this.pointY(prevPoint.total) : bottom;
      return {
        x: hasPrevious ? center + 1 : center - w / 2,
        y,
        w,
        h: Math.max(bottom - y, 0),
        p,
        prev: hasPrevious
          ? { x: center - w - 1, y: prevY, h: Math.max(bottom - prevY, 0), total: prevPoint?.total ?? 0 }
          : null,
      };
    });
  }

  get yTicks(): { y: number; label: string }[] {
    const ticks = 4;
    const innerH = this.chartH - this.padT - this.padB;
    const result = [];
    for (let i = 0; i <= ticks; i++) {
      const value = (this.maxChartValue * i) / ticks;
      result.push({
        y: this.padT + innerH - (i / ticks) * innerH,
        label: this.formatCompact(value),
      });
    }
    return result;
  }

  get xLabels(): { x: number; label: string }[] {
    const n = this.series.length;
    if (n === 0) return [];
    const maxLabels = 12;
    const step = Math.max(1, Math.ceil(n / maxLabels));
    return this.series
      .map((p, i) => ({ i, x: this.slotCenter(i), label: this.formatDate(p.date) }))
      .filter(({ i }) => i % step === 0 || i === n - 1)
      .map(({ x, label }) => ({ x, label }));
  }

  get chartBottom(): number {
    return this.chartH - this.padB;
  }

  // ---------- Anillos de métodos de pago ----------

  /**
   * Concentric 270° rings, one per payment method, sized by share of revenue.
   *
   * @return array<{ method: string, r: number, color: string, track: string, arc: string }>
   */
  get paymentRings(): { method: string; r: number; color: string; track: string; arc: string }[] {
    const total = this.paymentTotal;
    return this.paymentMethods.slice(0, this.ringRadii.length).map((item, i) => {
      const r = this.ringRadii[i];
      const span = 2 * Math.PI * r * 0.75;
      const share = total > 0 ? item.total_revenue / total : 0;
      return {
        method: item.payment_method,
        r,
        color: this.getPaymentColor(item.payment_method),
        track: `${span.toFixed(1)} 1000`,
        arc: `${(span * share).toFixed(1)} 1000`,
      };
    });
  }

  // ---------- Top productos ----------

  get topMetricValue(): (p: TopProductItem) => number {
    return (p) => (this.topMetric === 'revenue' ? p.total_revenue : p.total_quantity);
  }

  get maxTopValue(): number {
    if (this.topProducts.length === 0) return 1;
    return Math.max(...this.topProducts.map((p) => this.topMetricValue(p))) || 1;
  }

  topBarWidth(p: TopProductItem): number {
    return (this.topMetricValue(p) / this.maxTopValue) * 100;
  }

  topValueLabel(p: TopProductItem): string {
    return this.topMetric === 'revenue'
      ? this.formatMoney(p.total_revenue)
      : `${p.total_quantity} uds`;
  }

  // ---------- Inventario ----------

  inventoryPercent(part: 'normal' | 'low' | 'out'): number {
    const s = this.inventory?.summary;
    if (!s) return 0;
    const total = s.normal + s.low_stock + s.out_of_stock;
    if (total <= 0) return 0;
    const value = part === 'normal' ? s.normal : part === 'low' ? s.low_stock : s.out_of_stock;
    return (value / total) * 100;
  }

  /**
   * Dash settings for each gauge segment on a path normalized to pathLength = 100.
   *
   * @return array<{ part: string, dash: string, offset: number }>
   */
  get inventoryGaugeSegments(): { part: 'normal' | 'low' | 'out'; dash: string; offset: number }[] {
    let start = 0;
    return (['normal', 'low', 'out'] as const).map((part) => {
      const pct = this.inventoryPercent(part);
      const segment = { part, dash: `${pct} 100`, offset: -start };
      start += pct;
      return segment;
    });
  }

  // ---------- Ventas por cajero ----------

  get maxCashierTotal(): number {
    if (this.cashiers.length === 0) return 1;
    return Math.max(...this.cashiers.map((c) => c.total)) || 1;
  }

  get cashiersTotal(): number {
    return this.cashiers.reduce((sum, c) => sum + c.total, 0);
  }

  cashierShare(total: number): number {
    const sum = this.cashiersTotal;
    return sum > 0 ? Math.round((total / sum) * 100) : 0;
  }

  // ---------- Ventas por hora ----------

  get maxHourTotal(): number {
    const hours = this.hoursData?.hours ?? [];
    if (hours.length === 0) return 1;
    return Math.max(...hours.map((h) => h.total)) || 1;
  }

  get peakHour(): number | null {
    const hours = this.hoursData?.hours ?? [];
    if (hours.length === 0 || this.maxHourTotal <= 0) return null;
    return hours.reduce((a, b) => (b.total > a.total ? b : a)).hour;
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
}
