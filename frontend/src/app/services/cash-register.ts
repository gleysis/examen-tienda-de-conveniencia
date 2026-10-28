import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface CashRegister {
  id: number;
  name: string;
  location: string | null;
  user_id: number | null;
  opening_balance: number;
  current_balance: number;
  cash_sales: number;
  card_sales: number;
  transfer_sales: number;
  total_sales: number;
  expected_closing: number;
  actual_closing: number | null;
  difference: number | null;
  status: string;
  opened_at: string | null;
  closed_at: string | null;
  notes: string | null;
}

interface CashRegistersResponse {
  cash_registers: CashRegister[];
}

interface CashRegisterResponse {
  cash_register: CashRegister;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class CashRegisterService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getCashRegisters(params?: any): Observable<CashRegistersResponse> {
    return this.http.get<CashRegistersResponse>(`${this.apiUrl}/cash-registers`, { params });
  }

  getCashRegister(id: number): Observable<CashRegisterResponse> {
    return this.http.get<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}`);
  }

  getCurrentCashRegister(): Observable<CashRegisterResponse> {
    return this.http.get<CashRegisterResponse>(`${this.apiUrl}/cash-registers/current`);
  }

  createCashRegister(cashRegister: any): Observable<CashRegisterResponse> {
    return this.http.post<CashRegisterResponse>(`${this.apiUrl}/cash-registers`, cashRegister);
  }

  updateCashRegister(id: number, cashRegister: any): Observable<CashRegisterResponse> {
    return this.http.put<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}`, cashRegister);
  }

  openCashRegister(id: number, openingBalance: number): Observable<CashRegisterResponse> {
    return this.http.post<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}/open`, {
      opening_balance: openingBalance,
    });
  }

  closeCashRegister(id: number, actualClosing: number): Observable<CashRegisterResponse> {
    return this.http.post<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}/close`, {
      actual_closing: actualClosing,
    });
  }

  deleteCashRegister(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/cash-registers/${id}`);
  }
}
