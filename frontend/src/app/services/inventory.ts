import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface InventoryMovement {
  id: number;
  product_id: number;
  user_id: number;
  type: string;
  quantity: number;
  previous_stock: number;
  new_stock: number;
  reference: string;
  notes: string;
  product: any;
  user: any;
}

interface MovementsResponse {
  movements: InventoryMovement[];
}

interface MovementResponse {
  movement?: InventoryMovement;
  message?: string;
  error?: string;
}

interface ReportResponse {
  products: any[];
  summary: {
    total_products: number;
    total_stock: number;
    total_value: number;
    low_stock_count: number;
  };
}

@Injectable({
  providedIn: 'root',
})
export class InventoryService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getMovements(params?: any): Observable<MovementsResponse> {
    return this.http.get<MovementsResponse>(`${this.apiUrl}/inventory`, { params });
  }

  getMovement(id: number): Observable<MovementResponse> {
    return this.http.get<MovementResponse>(`${this.apiUrl}/inventory/${id}`);
  }

  createMovement(movement: any): Observable<MovementResponse> {
    return this.http.post<MovementResponse>(`${this.apiUrl}/inventory`, movement);
  }

  getLowStock(): Observable<{ products: any[] }> {
    return this.http.get<{ products: any[] }>(`${this.apiUrl}/inventory/low-stock`);
  }

  getReport(params?: any): Observable<ReportResponse> {
    return this.http.get<ReportResponse>(`${this.apiUrl}/inventory/report`, { params });
  }
}
