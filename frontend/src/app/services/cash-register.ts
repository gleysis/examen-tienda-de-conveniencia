import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PaginatedResponse } from './pagination';

export interface UserSummary {
  id: number;
  name: string;
}

export interface CashCount {
  id: number;
  cash_register_session_id: number;
  user_id: number | null;
  expected_amount: number;
  counted_amount: number;
  difference: number;
  type: string;
  notes: string | null;
  created_at: string;
}

export interface CashRegisterSession {
  id: number;
  cash_register_id: number;
  user_id: number | null;
  opened_by: number | null;
  closed_by: number | null;
  opening_balance: number;
  cash_sales: number;
  card_sales: number;
  transfer_sales: number;
  total_sales: number;
  expected_closing: number;
  status: 'open' | 'closed';
  opened_at: string | null;
  closed_at: string | null;
  notes: string | null;
  cash_register?: { id: number; name: string } | null;
  user?: UserSummary | null;
  opened_by_user?: UserSummary | null;
  closed_by_user?: UserSummary | null;
  cash_count?: CashCount | null;
}

export interface CashRegister {
  id: number;
  name: string;
  location: string | null;
  user_id: number | null;
  status: 'open' | 'closed';
  notes: string | null;
  user?: UserSummary | null;
  current_session?: CashRegisterSession | null;
}

export type CashMovementType = 'income' | 'withdrawal' | 'expense';

export interface CashMovement {
  id: number;
  cash_register_id: number;
  cash_register_session_id: number;
  user_id: number;
  type: CashMovementType;
  amount: number;
  reason: string;
  created_at: string;
  user?: UserSummary | null;
}

export interface ResponsibleUser extends UserSummary {
  has_open_session: boolean;
}

interface CashRegisterResponse {
  cash_register: CashRegister | null;
  session?: CashRegisterSession;
  message?: string;
}

@Injectable({
  providedIn: 'root',
})
export class CashRegisterService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getCashRegisters(params?: any): Observable<PaginatedResponse<CashRegister>> {
    return this.http.get<PaginatedResponse<CashRegister>>(`${this.apiUrl}/cash-registers`, { params });
  }

  getCashRegister(id: number): Observable<CashRegisterResponse> {
    return this.http.get<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}`);
  }

  /** The drawer assigned to the current user, including its live session when open. */
  getCurrentCashRegister(): Observable<CashRegisterResponse> {
    return this.http.get<CashRegisterResponse>(`${this.apiUrl}/cash-registers/current`);
  }

  createCashRegister(cashRegister: { name: string; location?: string; user_id?: number | null }): Observable<CashRegisterResponse> {
    return this.http.post<CashRegisterResponse>(`${this.apiUrl}/cash-registers`, cashRegister);
  }

  updateCashRegister(id: number, cashRegister: any): Observable<CashRegisterResponse> {
    return this.http.put<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}`, cashRegister);
  }

  /** Starts a new session. `userId` lets a supervisor open the drawer for a cashier. */
  openCashRegister(id: number, openingBalance: number, userId?: number | null): Observable<CashRegisterResponse> {
    return this.http.post<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}/open`, {
      opening_balance: openingBalance,
      ...(userId ? { user_id: userId } : {}),
    });
  }

  /** Closes the open session recording the counted cash (arqueo). */
  closeCashRegister(id: number, actualClosing: number): Observable<CashRegisterResponse> {
    return this.http.post<CashRegisterResponse>(`${this.apiUrl}/cash-registers/${id}/close`, {
      actual_closing: actualClosing,
    });
  }

  deleteCashRegister(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/cash-registers/${id}`);
  }

  getSessions(params?: any): Observable<PaginatedResponse<CashRegisterSession>> {
    return this.http.get<PaginatedResponse<CashRegisterSession>>(`${this.apiUrl}/cash-registers/sessions`, { params });
  }

  getSessionMovements(sessionId: number): Observable<{ movements: CashMovement[] }> {
    return this.http.get<{ movements: CashMovement[] }>(`${this.apiUrl}/cash-registers/sessions/${sessionId}/movements`);
  }

  getResponsibleUsers(): Observable<{ users: ResponsibleUser[] }> {
    return this.http.get<{ users: ResponsibleUser[] }>(`${this.apiUrl}/cash-registers/responsible-users`);
  }

  createMovement(movement: {
    type: CashMovementType;
    amount: number;
    reason: string;
    cash_register_id?: number;
  }): Observable<{ movement: CashMovement; expected_closing: number }> {
    return this.http.post<{ movement: CashMovement; expected_closing: number }>(`${this.apiUrl}/cash-movements`, movement);
  }
}
