import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Supplier {
  id: number;
  name: string;
  contact_person: string | null;
  email: string | null;
  phone: string | null;
  tax_id: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  zip_code: string | null;
  country: string | null;
  payment_terms: number;
  credit_limit: number | null;
  current_balance: number;
  is_active: boolean;
  notes: string | null;
}

interface SuppliersResponse {
  suppliers: Supplier[];
}

interface SupplierResponse {
  supplier: Supplier;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class SupplierService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getSuppliers(params?: any): Observable<SuppliersResponse> {
    return this.http.get<SuppliersResponse>(`${this.apiUrl}/suppliers`, { params });
  }

  getSupplier(id: number): Observable<SupplierResponse> {
    return this.http.get<SupplierResponse>(`${this.apiUrl}/suppliers/${id}`);
  }

  createSupplier(supplier: any): Observable<SupplierResponse> {
    return this.http.post<SupplierResponse>(`${this.apiUrl}/suppliers`, supplier);
  }

  updateSupplier(id: number, supplier: any): Observable<SupplierResponse> {
    return this.http.put<SupplierResponse>(`${this.apiUrl}/suppliers/${id}`, supplier);
  }

  deleteSupplier(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/suppliers/${id}`);
  }
}
