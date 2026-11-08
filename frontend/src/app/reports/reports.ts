import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { InventoryService } from '../services/inventory';
import { ReturnRefundService } from '../services/return-refund';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { DateInput } from '../directives/date-input';
import { APP_PIPES } from '../pipes';
import { MoneyPipe } from '../pipes/money';
import { AppDatePipe } from '../pipes/app-date';

interface ReturnRefund {
  id: number;
  return_number: string;
  refund_amount: number;
  status: string;
  created_at?: string;
}

@Component({
  imports: [CommonModule, FormsModule, DateInput, Layout, ...APP_PIPES],
  selector: 'app-reports',
  styleUrl: './reports.css',
  templateUrl: './reports.html',
})
export class Reports implements OnInit, OnDestroy {
  salesOverview: any = null;
  returns: ReturnRefund[] = [];
  isLoading = false;
  errorMessage = '';
  activeTab = '';
  activeGroup = '';
  user: any;

  salesChartDays = 7;
  readonly chartW = 560;
  readonly chartH = 190;
  readonly padL = 52;
  readonly padR = 14;
  readonly padT = 12;
  readonly padB = 26;
  readonly categoryColors = ['#E91E83', '#6B21A8', '#FFC928', '#F8BBD0', '#34d399', '#94a3b8'];

  tabs = [
    { key: 'sales', label: 'Ventas', permission: 'sales.view', group: 'Ventas' },
    { key: 'returns', label: 'Devoluciones', permission: 'returns.view', group: 'Ventas' },
    { key: 'top-products', label: 'Top Productos', permission: 'reports.view', group: 'Ventas' },
    { key: 'payment-methods', label: 'Métodos de Pago', permission: 'reports.view', group: 'Ventas' },
    { key: 'cashier-sales', label: 'Ventas x Cajero', permission: 'sales.view', group: 'Caja' },
    { key: 'daily-summary', label: 'Resumen Diario', permission: 'sales.view', group: 'Caja' },
    { key: 'cashier-products', label: 'Productos x Cajero', permission: 'sales.view', group: 'Caja' },
    { key: 'voided', label: 'Anuladas', permission: 'sales.view', group: 'Caja' },
    { key: 'shifts', label: 'Turnos', permission: 'cash-registers.view', group: 'Caja' },
    { key: 'closures', label: 'Cierre de Caja', permission: 'cash-registers.view', group: 'Caja' },
    { key: 'discrepancies', label: 'Faltantes/Sobrantes', permission: 'cash-registers.view', group: 'Caja' },
    { key: 'cash-movements', label: 'Mov. Efectivo', permission: 'cash-registers.view', group: 'Caja' },
    { key: 'inventory', label: 'Resumen', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'products', label: 'Existencias', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'movements', label: 'Movimientos', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'low-stock', label: 'Bajo Stock', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'inbound', label: 'Entradas', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'losses', label: 'Mermas', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'valuation', label: 'Valoración', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'slow-moving', label: 'Sin Movimiento', permission: 'inventory.reports', group: 'Almacén' },
    { key: 'supplier-purchases', label: 'Compras x Proveedor', permission: 'inventory.reports', group: 'Almacén' },
  ];

  warehouseData: Record<string, any> = {};
  cashierData: Record<string, any> = {};
  cashiers: any[] = [];
  cashierFilters = { user_id: '', invoice: '', payment_method: '', status: '', type: '' };
  private cashiersLoaded = false;
  movementFilters = { type: '', product: '', user: '' };
  stockFilters = { category_id: '', supplier_id: '', search: '', status: '' };
  reportCategories: any[] = [];
  reportSuppliers: any[] = [];
  private filtersLoaded = false;

  private loadedTabs = new Set<string>();

  get visibleTabs() {
    return this.tabs.filter((tab) => this.authService.hasPermission(tab.permission));
  }

  get visibleGroups(): { label: string; tabs: any[] }[] {
    const groups = new Map<string, any[]>();
    for (const tab of this.visibleTabs) {
      if (!groups.has(tab.group)) {
        groups.set(tab.group, []);
      }
      groups.get(tab.group)!.push(tab);
    }
    return [...groups.entries()].map(([label, tabs]) => ({ label, tabs }));
  }

  get currentGroupTabs(): any[] {
    return this.visibleGroups.find((g) => g.label === this.activeGroup)?.tabs ?? this.visibleTabs;
  }

  setGroup(group: { label: string; tabs: any[] }): void {
    this.activeGroup = group.label;
    if (!group.tabs.some((t) => t.key === this.activeTab)) {
      this.setTab(group.tabs[0].key);
    }
  }

  inventorySummary: any = null;
  inventoryProducts: any[] = [];
  returnsSummary: any = null;
  topProductsData: any = null;
  paymentMethodsData: any = null;

  dateFilter = {
    date_from: '',
    date_to: '',
  };

  constructor(
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
    
    // Rango por defecto: día actual (fecha local, no UTC)
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    this.dateFilter = {
      date_from: today,
      date_to: today,
    };

    const firstTab = this.visibleTabs[0];
    if (firstTab) {
      this.activeTab = firstTab.key;
      this.activeGroup = firstTab.group;
      this.loadTabData(firstTab.key);
    }

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/reports' || event.urlAfterRedirects === '/reports') {
        console.log('Navigated to reports, reloading data');
        this.loadedTabs.clear();
        if (this.activeTab) {
          this.loadTabData(this.activeTab);
        }
      }
    });
  }

  loadTabData(tab: string): void {
    this.loadedTabs.add(tab);
    switch (tab) {
      case 'sales':
        this.loadSalesOverview();
        break;
      case 'inventory':
        this.loadInventoryReport();
        break;
      case 'products':
        this.loadInventoryReport();
        this.loadReportFilters();
        break;
      case 'movements':
      case 'low-stock':
      case 'inbound':
      case 'losses':
      case 'valuation':
      case 'slow-moving':
      case 'supplier-purchases':
        this.loadWarehouseReport(tab);
        break;
      case 'cashier-sales':
      case 'daily-summary':
      case 'cashier-products':
      case 'voided':
      case 'shifts':
      case 'closures':
      case 'discrepancies':
      case 'cash-movements':
        this.loadCashierReport(tab);
        this.loadCashiers();
        break;
      case 'returns':
        this.loadReturns();
        break;
      case 'top-products':
        this.loadTopProducts();
        break;
      case 'payment-methods':
        this.loadPaymentMethods();
        break;
    }
  }

  ngOnDestroy(): void {
    clearTimeout(this.filterDebounce);
  }

  loadSalesOverview(): void {
    this.isLoading = true;
    const params: any = {};
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;

    this.http.get('http://127.0.0.1:8000/api/cashier-reports/sales-overview', { params }).subscribe({
      next: (response) => {
        this.salesOverview = response;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading sales overview:', error);
        this.errorMessage = 'Error al cargar ventas';
        this.loadedTabs.delete('sales');
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
        this.inventoryProducts = response.products || [];
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading inventory report:', error);
        this.loadedTabs.delete('inventory');
        this.loadedTabs.delete('products');
        this.errorMessage = 'Error al cargar el reporte';
        this.cdr.detectChanges();
      },
    });
  }


  loadWarehouseReport(key: string): void {
    const params: any = {};
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;
    if (key === 'movements') {
      if (this.movementFilters.type) params.type = this.movementFilters.type;
      if (this.movementFilters.product) params.product = this.movementFilters.product;
      if (this.movementFilters.user) params.user = this.movementFilters.user;
    }

    this.http.get(`http://127.0.0.1:8000/api/warehouse-reports/${key}`, { params }).subscribe({
      next: (response) => {
        this.warehouseData[key] = response;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error(`Error loading ${key} report:`, error);
        this.errorMessage = 'Error al cargar el reporte';
        this.loadedTabs.delete(key);
        this.cdr.detectChanges();
      },
    });
  }

  loadCashierReport(key: string): void {
    const endpoints: Record<string, string> = {
      'cashier-sales': 'sales',
      'daily-summary': 'daily-summary',
      'cashier-products': 'products',
      voided: 'voided',
      shifts: 'shifts',
      closures: 'closures',
      discrepancies: 'discrepancies',
      'cash-movements': 'cash-movements',
    };

    const params: any = {};
    if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
    if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;
    if (this.cashierFilters.user_id) params.user_id = this.cashierFilters.user_id;
    if (key === 'cashier-sales' || key === 'voided' || key === 'shifts') {
      if (this.cashierFilters.status) params.status = this.cashierFilters.status;
    }
    if (key === 'cashier-sales') {
      if (this.cashierFilters.invoice) params.invoice = this.cashierFilters.invoice;
      if (this.cashierFilters.payment_method) params.payment_method = this.cashierFilters.payment_method;
    }
    if (key === 'cash-movements' && this.cashierFilters.type) {
      params.type = this.cashierFilters.type;
    }

    this.http
      .get(`http://127.0.0.1:8000/api/cashier-reports/${endpoints[key]}`, { params })
      .subscribe({
        next: (response) => {
          this.cashierData[key] = response;
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error(`Error loading ${key} report:`, error);
          this.errorMessage = 'Error al cargar el reporte';
          this.loadedTabs.delete(key);
          this.cdr.detectChanges();
        },
      });
  }

  loadCashiers(): void {
    if (this.cashiersLoaded) {
      return;
    }
    this.cashiersLoaded = true;

    this.http.get('http://127.0.0.1:8000/api/cashier-reports/cashiers').subscribe({
      next: (response: any) => {
        this.cashiers = response.cashiers || [];
        this.cdr.detectChanges();
      },
      error: () => {},
    });
  }

  getSaleStatusLabel(status: string): string {
    const labels: any = {
      completed: 'Completada',
      cancelled: 'Anulada',
      refunded: 'Devuelta',
      pending: 'Pendiente',
    };
    return labels[status] || status;
  }

  getRegisterStatusLabel(status: string): string {
    return status === 'open' ? 'Abierta' : 'Cerrada';
  }

  getCashMovementLabel(type: string): string {
    const labels: any = {
      income: 'Ingreso',
      withdrawal: 'Retiro',
      expense: 'Gasto',
    };
    return labels[type] || type;
  }

  formatWorked(minutes: number | null): string {
    if (minutes === null || minutes === undefined) {
      return '—';
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  }

  loadReportFilters(): void {
    if (this.filtersLoaded) {
      return;
    }
    this.filtersLoaded = true;

    this.http.get('http://127.0.0.1:8000/api/categories').subscribe({
      next: (response: any) => {
        this.reportCategories = response.categories || [];
        this.cdr.detectChanges();
      },
      error: () => {},
    });

    this.http.get('http://127.0.0.1:8000/api/suppliers', { params: { per_page: 100 } }).subscribe({
      next: (response: any) => {
        this.reportSuppliers = response.data || [];
        this.cdr.detectChanges();
      },
      error: () => {},
    });
  }

  get filteredStockProducts(): any[] {
    const f = this.stockFilters;
    const q = f.search.trim().toLowerCase();
    return this.inventoryProducts.filter((p) => {
      const status = this.getStockStatus(p);
      return (
        (!f.category_id || p.category_id == f.category_id) &&
        (!f.supplier_id || p.supplier_id == f.supplier_id) &&
        (!f.status || status === f.status) &&
        (!q || p.name.toLowerCase().includes(q) || p.sku?.toLowerCase().includes(q))
      );
    });
  }

  getStockStatus(product: any): 'low' | 'over' | 'ok' {
    if (product.min_stock !== null && product.stock <= product.min_stock) {
      return 'low';
    }
    if (product.max_stock !== null && product.stock >= product.max_stock) {
      return 'over';
    }
    return 'ok';
  }

  getMovementTypeLabel(type: string): string {
    return type === 'entry' ? 'Entrada' : 'Salida';
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
        this.loadedTabs.delete('returns');
        this.errorMessage = 'Error al cargar devoluciones';
        this.cdr.detectChanges();
      },
    });
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
        this.loadedTabs.delete('top-products');
        this.errorMessage = 'Error al cargar el reporte';
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
        this.loadedTabs.delete('payment-methods');
        this.errorMessage = 'Error al cargar el reporte';
        this.cdr.detectChanges();
      },
    });
  }

  getTotalQuantitySold(): number {
    if (!this.topProductsData?.top_products) return 0;
    return this.topProductsData.top_products.reduce(
      (sum: number, item: any) => sum + Number(item.total_quantity || 0),
      0
    );
  }

  getTotalRevenue(): number {
    if (!this.topProductsData?.top_products) return 0;
    return this.topProductsData.top_products.reduce((sum: number, item: any) => sum + item.total_revenue, 0);
  }

  getTotalTransactions(): number {
    if (!this.paymentMethodsData?.sales_by_payment_method) return 0;
    return this.paymentMethodsData.sales_by_payment_method.reduce(
      (sum: number, item: any) => sum + Number(item.total_transactions || 0),
      0
    );
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

  // ---------- Gráfica de líneas: ventas por día ----------

  get dailySeries(): { date: string; total: number; invoices: number }[] {
    const daily: any[] = this.salesOverview?.daily ?? [];
    return daily.slice(-this.salesChartDays);
  }

  get maxChartValue(): number {
    let max = 0;
    for (const p of this.dailySeries) max = Math.max(max, p.total);
    return max > 0 ? max * 1.15 : 1;
  }

  private pointX(index: number): number {
    const n = this.dailySeries.length;
    const innerW = this.chartW - this.padL - this.padR;
    if (n <= 1) return this.padL + innerW / 2;
    return this.padL + (index / (n - 1)) * innerW;
  }

  private pointY(value: number): number {
    const innerH = this.chartH - this.padT - this.padB;
    return this.padT + innerH - (value / this.maxChartValue) * innerH;
  }

  get linePoints(): { x: number; y: number; p: { date: string; total: number } }[] {
    return this.dailySeries.map((p, i) => ({ x: this.pointX(i), y: this.pointY(p.total), p }));
  }

  get linePath(): string {
    const pts = this.linePoints;
    if (pts.length === 0) return '';
    return pts.map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x.toFixed(1)},${pt.y.toFixed(1)}`).join(' ');
  }

  get areaPath(): string {
    const pts = this.linePoints;
    if (pts.length === 0) return '';
    const bottom = this.chartH - this.padB;
    const body = this.linePath;
    return `${body} L${pts[pts.length - 1].x.toFixed(1)},${bottom} L${pts[0].x.toFixed(1)},${bottom} Z`;
  }

  get chartBottom(): number {
    return this.chartH - this.padB;
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
    const pts = this.linePoints;
    if (pts.length === 0) return [];
    const step = Math.max(1, Math.ceil(pts.length / 7));
    return pts
      .filter((_, i) => i % step === 0 || i === pts.length - 1)
      .map((pt) => ({ x: pt.x, label: this.formatShortDate(pt.p.date) }));
  }

  formatShortDate(dateStr: string): string {
    if (!dateStr) return '';
    const [, m, d] = dateStr.split('-');
    return `${d}/${m}`;
  }

  formatCompact(value: number): string {
    if (value >= 1000) {
      return (value / 1000).toFixed(value >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'k';
    }
    return String(Math.round(value));
  }

  private readonly moneyPipe = new MoneyPipe();
  private readonly datePipe = new AppDatePipe();

  formatMoney(value: number | null | undefined): string {
    return this.moneyPipe.transform(value);
  }

  formatDateTime(value: string | null | undefined): string {
    return this.datePipe.transform(value, 'datetime');
  }

  // ---------- Dona: ventas por categoría ----------

  get categorySlices(): { name: string; total: number; percentage: number; color: string }[] {
    const rows: any[] = this.salesOverview?.by_category ?? [];
    const top = rows.slice(0, 4);
    const rest = rows.slice(4);
    const slices = top.map((r, i) => ({
      name: r.name,
      total: r.total,
      percentage: r.percentage,
      color: this.categoryColors[i],
    }));
    if (rest.length > 0) {
      slices.push({
        name: 'Otras',
        total: rest.reduce((s: number, r: any) => s + r.total, 0),
        percentage: rest.reduce((s: number, r: any) => s + r.percentage, 0),
        color: this.categoryColors[5],
      });
    }
    return slices;
  }

  get categoryDonut(): string {
    const slices = this.categorySlices;
    if (slices.length === 0) return 'conic-gradient(var(--color-border) 0% 100%)';
    let start = 0;
    const stops = slices.map((s) => {
      const stop = `${s.color} ${start}% ${start + s.percentage}%`;
      start += s.percentage;
      return stop;
    });
    return `conic-gradient(${stops.join(', ')})`;
  }

  get categoryTotal(): number {
    return this.categorySlices.reduce((s, c) => s + c.total, 0);
  }

  get topProductName(): string {
    return this.salesOverview?.top_product?.name ?? 'N/A';
  }

  private filterDebounce: any = null;

  onFilter(): void {
    this.errorMessage = '';
    clearTimeout(this.filterDebounce);
    this.filterDebounce = setTimeout(() => {
      this.loadedTabs.clear();
      this.loadTabData(this.activeTab);
      this.cdr.detectChanges();
    }, 150);
  }

  setTab(tab: string): void {
    this.activeTab = tab;
    if (!this.loadedTabs.has(tab)) {
      this.loadTabData(tab);
    }
  }

  exportCurrentTab(): void {
    switch (this.activeTab) {
      case 'sales':
        this.exportReport('sales');
        break;
      case 'cashier-sales':
        this.exportCsv(
          'ventas-por-cajero',
          ['Factura', 'Fecha', 'Cajero', 'Items', 'Subtotal', 'Impuesto', 'Descuento', 'Total', 'Método de Pago', 'Estado'],
          (this.cashierData['cashier-sales']?.sales || []).map((s: any) => [
            s.invoice_number, this.formatDateTime(s.created_at), s.user?.name || '',
            s.items_count, s.subtotal, s.tax, s.discount, s.total,
            this.getPaymentMethodLabel(s.payment_method), this.getSaleStatusLabel(s.status),
          ]),
          this.dateMeta([
            ['Facturas', this.cashierData['cashier-sales']?.summary?.count ?? ''],
            ['Total Vendido', this.formatMoney(this.cashierData['cashier-sales']?.summary?.total)],
          ])
        );
        break;
      case 'daily-summary':
        this.exportCsv(
          'resumen-diario',
          ['Fecha', 'Facturas', 'Subtotal', 'Descuentos', 'Impuestos', 'Total', 'Anuladas', 'Venta Neta', 'Efectivo', 'Tarjeta', 'Transferencia'],
          (this.cashierData['daily-summary']?.days || []).map((d: any) => [
            d.date, d.invoices, d.subtotal, d.discount, d.tax, d.total, d.cancelled,
            d.net, d.cash, d.card, d.transfer,
          ]),
          this.dateMeta([
            ['Facturas', this.cashierData['daily-summary']?.summary?.invoices ?? ''],
            ['Total Vendido', this.formatMoney(this.cashierData['daily-summary']?.summary?.total)],
            ['Venta Neta', this.formatMoney(this.cashierData['daily-summary']?.summary?.net)],
          ])
        );
        break;
      case 'cashier-products':
        this.exportCsv(
          'productos-por-cajero',
          ['Producto', 'SKU', 'Cajero', 'Cantidad', 'Precio Unit.', 'Descuento', 'Importe Total'],
          (this.cashierData['cashier-products']?.items || []).map((i: any) => [
            i.product, i.sku, i.cashier, i.quantity, i.unit_price, i.discount, i.total,
          ]),
          this.dateMeta([
            ['Unidades', this.cashierData['cashier-products']?.summary?.units ?? ''],
            ['Total', this.formatMoney(this.cashierData['cashier-products']?.summary?.total)],
          ])
        );
        break;
      case 'voided':
        this.exportCsv(
          'anuladas-y-devoluciones',
          ['Tipo', 'Número', 'Fecha', 'Usuario', 'Monto', 'Estado', 'Motivo'],
          [
            ...(this.cashierData['voided']?.voided_sales || []).map((s: any) => [
              'Anulada', s.invoice_number, this.formatDateTime(s.created_at),
              s.user?.name || '', s.total, this.getSaleStatusLabel(s.status), s.notes || '',
            ]),
            ...(this.cashierData['voided']?.returns || []).map((r: any) => [
              'Devolución', r.return_number, this.formatDateTime(r.created_at),
              r.user?.name || '', r.refund_amount, this.getStatusLabel(r.status), r.reason || '',
            ]),
          ],
          this.dateMeta([
            ['Anuladas', this.cashierData['voided']?.summary?.voided_count ?? ''],
            ['Monto Anulado', this.formatMoney(this.cashierData['voided']?.summary?.voided_total)],
            ['Devoluciones', this.cashierData['voided']?.summary?.returns_count ?? ''],
            ['Monto Devuelto', this.formatMoney(this.cashierData['voided']?.summary?.returns_total)],
          ])
        );
        break;
      case 'shifts':
        this.exportCsv(
          'turnos-cajero',
          ['Caja', 'Cajero', 'Apertura', 'Cierre', 'Fondo Inicial', 'Total Vendido', 'Tiempo Trabajado', 'Estado'],
          (this.cashierData['shifts']?.shifts || []).map((s: any) => [
            s.name, s.user?.name || '', s.opened_at ? this.formatDateTime(s.opened_at) : '',
            s.closed_at ? this.formatDateTime(s.closed_at) : '—', s.opening_balance,
            s.total_sales, this.formatWorked(s.worked_minutes), this.getRegisterStatusLabel(s.status),
          ]),
          this.dateMeta([
            ['Turnos', this.cashierData['shifts']?.summary?.total ?? ''],
            ['Abiertos', this.cashierData['shifts']?.summary?.open ?? ''],
            ['Cerrados', this.cashierData['shifts']?.summary?.closed ?? ''],
            ['Total Vendido', this.formatMoney(this.cashierData['shifts']?.summary?.total_sold)],
          ])
        );
        break;
      case 'closures':
        this.exportCsv(
          'cierres-de-caja',
          ['Caja', 'Cajero', 'Apertura', 'Cierre', 'Fondo Inicial', 'Ventas Efectivo', 'Entradas', 'Salidas', 'Efectivo Esperado', 'Efectivo Contado', 'Diferencia'],
          (this.cashierData['closures']?.closures || []).map((c: any) => [
            c.name, c.user?.name || '', c.opened_at ? this.formatDateTime(c.opened_at) : '',
            c.closed_at ? this.formatDateTime(c.closed_at) : '', c.opening_balance,
            c.cash_sales, c.cash_in, c.cash_out, c.expected_closing, c.actual_closing, c.difference,
          ]),
          this.dateMeta([
            ['Cierres', this.cashierData['closures']?.summary?.total ?? ''],
            ['Ventas en Efectivo', this.formatMoney(this.cashierData['closures']?.summary?.cash_sales)],
            ['Diferencia Neta', this.formatMoney(this.cashierData['closures']?.summary?.difference)],
          ])
        );
        break;
      case 'discrepancies':
        this.exportCsv(
          'faltantes-y-sobrantes',
          ['Caja', 'Cajero', 'Fecha Cierre', 'Efectivo Esperado', 'Efectivo Contado', 'Diferencia', 'Tipo', 'Observaciones'],
          (this.cashierData['discrepancies']?.discrepancies || []).map((d: any) => [
            d.name, d.user?.name || '', d.closed_at ? this.formatDateTime(d.closed_at) : '',
            d.expected_closing, d.actual_closing, d.difference,
            d.difference < 0 ? 'Faltante' : 'Sobrante', d.notes || '',
          ]),
          this.dateMeta([
            ['Registros', this.cashierData['discrepancies']?.summary?.total ?? ''],
            ['Faltantes', this.cashierData['discrepancies']?.summary?.shortages ?? ''],
            ['Sobrantes', this.cashierData['discrepancies']?.summary?.overages ?? ''],
            ['Diferencia Neta', this.formatMoney(this.cashierData['discrepancies']?.summary?.net_difference)],
          ])
        );
        break;
      case 'cash-movements':
        this.exportCsv(
          'movimientos-efectivo',
          ['Fecha', 'Caja', 'Usuario', 'Tipo', 'Monto', 'Motivo'],
          (this.cashierData['cash-movements']?.movements || []).map((m: any) => [
            this.formatDateTime(m.created_at), m.cash_register?.name || '',
            m.user?.name || '', this.getCashMovementLabel(m.type), m.amount, m.reason || '',
          ]),
          this.dateMeta([
            ['Movimientos', this.cashierData['cash-movements']?.summary?.total ?? ''],
            ['Entradas', this.formatMoney(this.cashierData['cash-movements']?.summary?.income)],
            ['Salidas', this.formatMoney(this.cashierData['cash-movements']?.summary?.out)],
          ])
        );
        break;
      case 'inventory':
        this.exportCsv(
          'resumen-inventario',
          ['SKU', 'Producto', 'Categoría', 'Stock', 'Stock Mínimo', 'Costo', 'Precio', 'Valor en Stock'],
          this.inventoryProducts.map((p: any) => [
            p.sku, p.name, p.category?.name || '', p.stock, p.min_stock ?? '',
            p.cost, p.price, p.stock * p.cost,
          ]),
          [
            ['Total Productos', this.inventorySummary?.total_products ?? ''],
            ['Total Stock', this.inventorySummary?.total_stock ?? ''],
            ['Valor Total', this.formatMoney(this.inventorySummary?.total_value)],
            ['Stock Bajo', this.inventorySummary?.low_stock_count ?? ''],
          ]
        );
        break;
      case 'products':
        this.exportCsv(
          'existencias',
          ['SKU', 'Producto', 'Categoría', 'Stock', 'Stock Mínimo', 'Costo', 'Precio', 'Valor en Stock'],
          this.filteredStockProducts.map((p: any) => [
            p.sku, p.name, p.category?.name || '', p.stock, p.min_stock ?? '',
            p.cost, p.price, p.stock * p.cost,
          ]),
          [
            ['Total Productos', this.inventorySummary?.total_products ?? ''],
            ['Total Stock', this.inventorySummary?.total_stock ?? ''],
            ['Valor Total', this.formatMoney(this.inventorySummary?.total_value)],
            ['Stock Bajo', this.inventorySummary?.low_stock_count ?? ''],
            ['Mostrados', this.filteredStockProducts.length],
          ]
        );
        break;
      case 'movements':
        this.exportCsv(
          'movimientos-almacen',
          ['Fecha', 'Producto', 'SKU', 'Tipo', 'Cantidad', 'Stock Anterior', 'Stock Nuevo', 'Usuario', 'Referencia', 'Notas'],
          (this.warehouseData['movements']?.movements || []).map((m: any) => [
            this.formatDateTime(m.created_at), m.product?.name || '', m.product?.sku || '',
            this.getMovementTypeLabel(m.type), m.quantity, m.previous_stock, m.new_stock,
            m.user?.name || '', m.reference || '', m.notes || '',
          ]),
          this.dateMeta([
            ['Movimientos', this.warehouseData['movements']?.summary?.total ?? ''],
            ['Unidades Entrada', this.warehouseData['movements']?.summary?.entries ?? ''],
            ['Unidades Salida', this.warehouseData['movements']?.summary?.exits ?? ''],
          ])
        );
        break;
      case 'low-stock':
        this.exportCsv(
          'bajo-stock',
          ['SKU', 'Producto', 'Categoría', 'Proveedor', 'Stock', 'Stock Mínimo', 'Faltante', 'Sugerido'],
          (this.warehouseData['low-stock']?.products || []).map((p: any) => [
            p.sku, p.name, p.category?.name || '', p.supplier?.name || '',
            p.stock, p.min_stock, p.missing_qty, p.suggested_qty,
          ]),
          [
            ['Productos Bajo Mínimo', this.warehouseData['low-stock']?.summary?.count ?? ''],
            ['Unidades Faltantes', this.warehouseData['low-stock']?.summary?.missing_total ?? ''],
            ['Costo Estimado Reposición', this.formatMoney(this.warehouseData['low-stock']?.summary?.estimated_cost)],
          ]
        );
        break;
      case 'inbound':
        this.exportCsv(
          'entradas-mercancia',
          ['Fecha', 'Producto', 'SKU', 'Cantidad', 'Costo Unit.', 'Costo Total', 'Usuario', 'Referencia', 'Notas'],
          (this.warehouseData['inbound']?.movements || []).map((m: any) => [
            this.formatDateTime(m.created_at), m.product?.name || '', m.product?.sku || '',
            m.quantity, m.unit_cost, m.total_cost, m.user?.name || '', m.reference || '', m.notes || '',
          ]),
          this.dateMeta([
            ['Recepciones', this.warehouseData['inbound']?.summary?.total ?? ''],
            ['Unidades Recibidas', this.warehouseData['inbound']?.summary?.units ?? ''],
            ['Costo Total', this.formatMoney(this.warehouseData['inbound']?.summary?.total_cost)],
          ])
        );
        break;
      case 'losses':
        this.exportCsv(
          'mermas',
          ['Fecha', 'Producto', 'SKU', 'Cantidad', 'Valor Pérdida', 'Usuario', 'Referencia', 'Motivo'],
          (this.warehouseData['losses']?.movements || []).map((m: any) => [
            this.formatDateTime(m.created_at), m.product?.name || '', m.product?.sku || '',
            m.quantity, m.loss_value, m.user?.name || '', m.reference || '', m.notes || '',
          ]),
          this.dateMeta([
            ['Registros', this.warehouseData['losses']?.summary?.total ?? ''],
            ['Unidades Perdidas', this.warehouseData['losses']?.summary?.units ?? ''],
            ['Valor de la Pérdida', this.formatMoney(this.warehouseData['losses']?.summary?.loss_value)],
          ])
        );
        break;
      case 'valuation':
        this.exportCsv(
          'valoracion-inventario',
          ['SKU', 'Producto', 'Categoría', 'Stock', 'Costo Unit.', 'Valor Total'],
          (this.warehouseData['valuation']?.items || []).map((i: any) => [
            i.sku, i.name, i.category, i.stock, i.cost, i.total_value,
          ]),
          [
            ['Valor Total Inventario', this.formatMoney(this.warehouseData['valuation']?.total_value)],
            ['Unidades Totales', this.warehouseData['valuation']?.total_stock ?? ''],
          ]
        );
        break;
      case 'slow-moving':
        this.exportCsv(
          'productos-sin-movimiento',
          ['SKU', 'Producto', 'Categoría', 'Stock', 'Último Movimiento', 'Días sin Movimiento', 'Valor Inmovilizado'],
          (this.warehouseData['slow-moving']?.items || []).map((i: any) => [
            i.sku, i.name, i.category, i.stock,
            i.last_movement_at ? this.formatDateTime(i.last_movement_at) : 'Nunca',
            i.days_idle, i.idle_value,
          ]),
          [
            ['Sin Movimiento +30 Días', this.warehouseData['slow-moving']?.summary?.over_30_days ?? ''],
            ['Valor Inmovilizado', this.formatMoney(this.warehouseData['slow-moving']?.summary?.idle_value)],
          ]
        );
        break;
      case 'supplier-purchases':
        this.exportCsv(
          'compras-por-proveedor',
          ['Proveedor', 'Entradas', 'Productos Distintos', 'Unidades', 'Costo Total', 'Última Entrada'],
          (this.warehouseData['supplier-purchases']?.suppliers || []).map((s: any) => [
            s.supplier, s.entries, s.products, s.units, s.total_cost,
            s.last_entry ? this.formatDateTime(s.last_entry) : '',
          ]),
          this.dateMeta([
            ['Unidades Ingresadas', this.warehouseData['supplier-purchases']?.summary?.units ?? ''],
            ['Costo Total', this.formatMoney(this.warehouseData['supplier-purchases']?.summary?.total_cost)],
          ])
        );
        break;
      case 'returns':
        this.exportCsv(
          'devoluciones',
          ['Número', 'Monto', 'Estado', 'Fecha'],
          this.returns.map((r) => [
            r.return_number,
            r.refund_amount,
            this.getStatusLabel(r.status),
            r.created_at ? this.formatDateTime(r.created_at) : '',
          ]),
          this.dateMeta([
            ['Total Devoluciones', this.returnsSummary?.total_returns ?? ''],
            ['Total Reembolsado', this.formatMoney(this.returnsSummary?.total_refunded)],
            ['Procesadas', this.returnsSummary?.processed_returns ?? ''],
            ['Pendientes', this.returnsSummary?.pending_returns ?? ''],
          ])
        );
        break;
      case 'top-products':
        this.exportCsv(
          'top-productos',
          ['Producto', 'SKU', 'Unidades Vendidas', 'Ingresos Totales'],
          (this.topProductsData?.top_products || []).map((item: any) => [
            item.product?.name || '',
            item.product?.sku || '',
            item.total_quantity,
            item.total_revenue,
          ]),
          this.dateMeta([
            ['Total Unidades Vendidas', this.getTotalQuantitySold()],
            ['Total Ingresos', this.formatMoney(this.getTotalRevenue())],
          ])
        );
        break;
      case 'payment-methods':
        this.exportCsv(
          'metodos-pago',
          ['Método de Pago', 'Transacciones', 'Ingresos Totales', 'Promedio por Transacción'],
          (this.paymentMethodsData?.sales_by_payment_method || []).map((item: any) => [
            this.getPaymentMethodLabel(item.payment_method),
            item.total_transactions,
            item.total_revenue,
            item.total_transactions ? (item.total_revenue / item.total_transactions).toFixed(2) : '0.00',
          ]),
          this.dateMeta([
            ['Total Transacciones', this.getTotalTransactions()],
            ['Total Ingresos', this.formatMoney(this.getTotalPaymentRevenue())],
          ])
        );
        break;
    }
  }

  exportReport(kind: 'sales' | 'inventory-movements' | 'inventory-stock'): void {
    const params: any = { format: 'xlsx' };
    if (kind !== 'inventory-stock') {
      if (this.dateFilter.date_from) params.date_from = this.dateFilter.date_from;
      if (this.dateFilter.date_to) params.date_to = this.dateFilter.date_to;
    }

    this.http
      .get(`http://127.0.0.1:8000/api/reports/${kind}/export`, {
        params,
        responseType: 'blob',
      })
      .subscribe({
        next: (blob) => {
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `${kind}.xlsx`;
          link.click();
          window.URL.revokeObjectURL(url);
        },
        error: (error) => {
          console.error('Error exporting report:', error);
          this.errorMessage = 'Error al exportar el reporte';
          this.cdr.detectChanges();
        },
      });
  }

  private dateMeta(extra: [string, string | number][] = []): [string, string | number][] {
    const meta: [string, string | number][] = [];
    if (this.dateFilter.date_from) {
      meta.push(['Desde', this.dateFilter.date_from]);
    }
    if (this.dateFilter.date_to) {
      meta.push(['Hasta', this.dateFilter.date_to]);
    }
    return [...meta, ...extra];
  }

  private exportCsv(
    filename: string,
    headers: string[],
    rows: (string | number)[][],
    meta: [string, string | number][] = []
  ): void {
    const escape = (value: string | number) =>
      String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const metaRows = meta
      .map(([label, value]) => `<tr><td><b>${escape(label)}</b></td><td>${escape(value)}</td></tr>`)
      .join('');
    const headerRow = `<tr>${headers.map((h) => `<th style="background:#E91E83;color:#fff;">${escape(h)}</th>`).join('')}</tr>`;
    const bodyRows = rows
      .map((row) => `<tr>${row.map((cell) => `<td>${escape(cell)}</td>`).join('')}</tr>`)
      .join('');

    const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="utf-8"></head>
<body>
<table border="1" cellspacing="0" cellpadding="4">
${metaRows}
${metaRows ? '<tr><td colspan="2"></td></tr>' : ''}
${headerRow}
${bodyRows}
</table>
</body>
</html>`;

    const blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${filename}.xls`;
    link.click();
    window.URL.revokeObjectURL(url);
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
