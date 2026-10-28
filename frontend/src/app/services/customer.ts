import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Customer {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  tax_id: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  zip_code: string | null;
  loyalty_points: number;
  total_purchases: number;
  total_visits: number;
  last_purchase_date: string | null;
  is_active: boolean;
  notes: string | null;
}

interface CustomersResponse {
  customers: Customer[];
}

interface CustomerResponse {
  customer: Customer;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class CustomerService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getCustomers(params?: any): Observable<CustomersResponse> {
    return this.http.get<CustomersResponse>(`${this.apiUrl}/customers`, { params });
  }

  getCustomer(id: number): Observable<CustomerResponse> {
    return this.http.get<CustomerResponse>(`${this.apiUrl}/customers/${id}`);
  }

  createCustomer(customer: any): Observable<CustomerResponse> {
    return this.http.post<CustomerResponse>(`${this.apiUrl}/customers`, customer);
  }

  updateCustomer(id: number, customer: any): Observable<CustomerResponse> {
    return this.http.put<CustomerResponse>(`${this.apiUrl}/customers/${id}`, customer);
  }

  deleteCustomer(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/customers/${id}`);
  }

  addLoyaltyPoints(id: number, points: number): Observable<CustomerResponse> {
    return this.http.post<CustomerResponse>(`${this.apiUrl}/customers/${id}/loyalty-points`, { points });
  }
}
