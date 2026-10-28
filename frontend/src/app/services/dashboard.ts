import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface DashboardData {
  total_sales_today: number;
  total_products: number;
  low_stock_products: number;
  active_users: number;
  sales_last_7_days: Array<{
    date: string;
    total: number;
  }>;
  top_categories: Array<{
    name: string;
    total_sold: number;
  }>;
}

interface TopProductsResponse {
  top_products: Array<{
    product: { name: string };
    total_quantity: number;
    total_revenue: number;
  }>;
}

interface SalesByPaymentResponse {
  sales_by_payment_method: Array<{
    payment_method: string;
    total_transactions: number;
    total_revenue: number;
  }>;
}

@Injectable({
  providedIn: 'root',
})
export class DashboardService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getDashboardData(): Observable<DashboardData> {
    return this.http.get<DashboardData>(`${this.apiUrl}/dashboard`);
  }

  getTopProducts(): Observable<TopProductsResponse> {
    return this.http.get<TopProductsResponse>(`${this.apiUrl}/dashboard/top-products`);
  }

  getSalesByPaymentMethod(): Observable<SalesByPaymentResponse> {
    return this.http.get<SalesByPaymentResponse>(`${this.apiUrl}/dashboard/sales-by-payment`);
  }
}
