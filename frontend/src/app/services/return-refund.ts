import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PaginatedResponse } from './pagination';

interface ReturnRefund {
  id: number;
  return_number: string;
  sale_id: number;
  sale_item_id: number;
  product_id: number;
  customer_id: number | null;
  user_id: number;
  quantity: number;
  refund_amount: number;
  reason: string;
  reason_description: string | null;
  status: string;
  refund_method: string;
  reference_number: string | null;
  processed_date: string | null;
  notes: string | null;
}

interface ReturnRefundResponse {
  return_refund: ReturnRefund;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class ReturnRefundService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getReturns(params?: any): Observable<PaginatedResponse<ReturnRefund>> {
    return this.http.get<PaginatedResponse<ReturnRefund>>(`${this.apiUrl}/returns`, { params });
  }

  getReturnRefund(id: number): Observable<ReturnRefundResponse> {
    return this.http.get<ReturnRefundResponse>(`${this.apiUrl}/returns/${id}`);
  }

  createReturnRefund(returnRefund: any): Observable<ReturnRefundResponse> {
    return this.http.post<ReturnRefundResponse>(`${this.apiUrl}/returns`, returnRefund);
  }

  updateReturnRefund(id: number, returnRefund: any): Observable<ReturnRefundResponse> {
    return this.http.put<ReturnRefundResponse>(`${this.apiUrl}/returns/${id}`, returnRefund);
  }

  processReturnRefund(id: number, data: any): Observable<ReturnRefundResponse> {
    return this.http.post<ReturnRefundResponse>(`${this.apiUrl}/returns/${id}/process`, data);
  }

  deleteReturnRefund(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/returns/${id}`);
  }
}
