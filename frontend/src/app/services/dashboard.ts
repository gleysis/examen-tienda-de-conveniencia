import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface DashboardCards {
  sales_today: number;
  invoices_today: number;
  sales_period: number;
  invoices_period: number;
  low_stock_count: number;
  out_of_stock_count: number;
  open_registers: number;
}

export interface MyRegister {
  id: number;
  name: string;
  opened_at: string;
  opening_balance: number;
  current_balance: number;
  cash_sales: number;
  card_sales: number;
  transfer_sales: number;
  total_sales: number;
}

export interface DashboardData {
  scope: 'own' | 'all';
  period: { from: string; to: string };
  cards: DashboardCards;
  my_register?: MyRegister | null;
}

export interface SalesPoint {
  date: string;
  total: number;
  invoices: number;
}

export interface SalesChartResponse {
  series: SalesPoint[];
  total: number;
  invoices: number;
  previous?: {
    from: string;
    to: string;
    series: SalesPoint[];
    total: number;
    invoices: number;
  };
}

export interface TopProductItem {
  product: { id: number; name: string; sku: string } | null;
  total_quantity: number;
  total_revenue: number;
}

export interface TopProductsResponse {
  metric: 'quantity' | 'revenue';
  top_products: TopProductItem[];
}

export interface PaymentMethodItem {
  payment_method: string;
  total_transactions: number;
  total_revenue: number;
  percentage: number;
}

export interface SalesByPaymentResponse {
  total_revenue: number;
  sales_by_payment_method: PaymentMethodItem[];
}

export interface StockAlertProduct {
  id: number;
  name: string;
  sku: string;
  stock: number;
  min_stock: number;
}

export interface InventoryStatusResponse {
  summary: {
    normal: number;
    low_stock: number;
    out_of_stock: number;
  };
  low_stock_products: StockAlertProduct[];
  out_of_stock_products: StockAlertProduct[];
}

export interface CashierSale {
  user_id: number;
  name: string;
  invoices: number;
  total: number;
}

export interface SalesByCashierResponse {
  scoped: boolean;
  cashiers: CashierSale[];
}

export interface HourSale {
  hour: number;
  total: number;
  invoices: number;
}

export interface SalesByHourResponse {
  hours: HourSale[];
}

export interface ExpiryProduct {
  id: number;
  name: string;
  sku: string;
  stock: number;
  expiry_date: string;
  days_left?: number;
  days_expired?: number;
}

export interface OpenRegisterAlert {
  id: number;
  name: string;
  user: string | null;
  opened_at: string;
  total_sales: number;
}

export interface DiscrepancyAlert {
  id: number;
  name: string;
  user: string | null;
  closed_at: string;
  expected_closing: number;
  actual_closing: number;
  difference: number;
}

export interface DashboardAlerts {
  low_stock: StockAlertProduct[];
  out_of_stock: StockAlertProduct[];
  expiring_soon: ExpiryProduct[];
  expired: ExpiryProduct[];
  open_registers: OpenRegisterAlert[];
  discrepancies: DiscrepancyAlert[];
  can_view_discrepancies: boolean;
}

export interface DashboardFilters {
  date_from?: string;
  date_to?: string;
  compare?: boolean;
  metric?: 'quantity' | 'revenue';
  limit?: number;
}

@Injectable({
  providedIn: 'root',
})
export class DashboardService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  private buildParams(filters: DashboardFilters): HttpParams {
    let params = new HttpParams();
    if (filters.date_from) params = params.set('date_from', filters.date_from);
    if (filters.date_to) params = params.set('date_to', filters.date_to);
    if (filters.compare) params = params.set('compare', '1');
    if (filters.metric) params = params.set('metric', filters.metric);
    if (filters.limit) params = params.set('limit', String(filters.limit));
    return params;
  }

  getDashboardData(filters: DashboardFilters = {}): Observable<DashboardData> {
    return this.http.get<DashboardData>(`${this.apiUrl}/dashboard`, {
      params: this.buildParams(filters),
    });
  }

  getSalesChart(filters: DashboardFilters = {}): Observable<SalesChartResponse> {
    return this.http.get<SalesChartResponse>(`${this.apiUrl}/dashboard/sales-chart`, {
      params: this.buildParams(filters),
    });
  }

  getTopProducts(filters: DashboardFilters = {}): Observable<TopProductsResponse> {
    return this.http.get<TopProductsResponse>(`${this.apiUrl}/dashboard/top-products`, {
      params: this.buildParams(filters),
    });
  }

  getSalesByPaymentMethod(filters: DashboardFilters = {}): Observable<SalesByPaymentResponse> {
    return this.http.get<SalesByPaymentResponse>(`${this.apiUrl}/dashboard/sales-by-payment`, {
      params: this.buildParams(filters),
    });
  }

  getInventoryStatus(): Observable<InventoryStatusResponse> {
    return this.http.get<InventoryStatusResponse>(`${this.apiUrl}/dashboard/inventory-status`);
  }

  getSalesByCashier(filters: DashboardFilters = {}): Observable<SalesByCashierResponse> {
    return this.http.get<SalesByCashierResponse>(`${this.apiUrl}/dashboard/sales-by-cashier`, {
      params: this.buildParams(filters),
    });
  }

  getSalesByHour(filters: DashboardFilters = {}): Observable<SalesByHourResponse> {
    return this.http.get<SalesByHourResponse>(`${this.apiUrl}/dashboard/sales-by-hour`, {
      params: this.buildParams(filters),
    });
  }

  getAlerts(): Observable<DashboardAlerts> {
    return this.http.get<DashboardAlerts>(`${this.apiUrl}/dashboard/alerts`);
  }
}
