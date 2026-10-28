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

@Injectable({
  providedIn: 'root',
})
export class DashboardService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getDashboardData(): Observable<DashboardData> {
    return this.http.get<DashboardData>(`${this.apiUrl}/dashboard`);
  }
}
