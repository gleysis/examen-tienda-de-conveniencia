import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PaginatedResponse } from './pagination';

interface SaleItem {
  product_id: number;
  quantity: number;
  unit_price: number;
  discount?: number;
}

export interface Sale {
  id: number;
  invoice_number: string;
  user_id: number;
  cash_register_id: number | null;
  subtotal: number;
  tax: number;
  discount: number;
  total: number;
  payment_method: string;
  status: string;
  notes: string | null;
  user?: any;
  cash_register?: any;
  items?: any[];
  created_at: string;
  updated_at?: string;
}

interface SaleResponse {
  sale?: Sale;
  out_of_stock_products?: string[];
  message?: string;
  error?: string;
}

@Injectable({
  providedIn: 'root',
})
export class SaleService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getSales(params?: any): Observable<PaginatedResponse<Sale>> {
    return this.http.get<PaginatedResponse<Sale>>(`${this.apiUrl}/sales`, { params });
  }

  getSale(id: number): Observable<SaleResponse> {
    return this.http.get<SaleResponse>(`${this.apiUrl}/sales/${id}`);
  }

  createSale(sale: any): Observable<SaleResponse> {
    return this.http.post<SaleResponse>(`${this.apiUrl}/sales`, sale);
  }

  updateSale(id: number, sale: any): Observable<SaleResponse> {
    return this.http.put<SaleResponse>(`${this.apiUrl}/sales/${id}`, sale);
  }

  deleteSale(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/sales/${id}`);
  }
}
