import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Discount {
  id: number;
  code: string;
  name: string;
  type: string;
  value: number;
  minimum_purchase: number | null;
  maximum_discount: number | null;
  usage_limit: number | null;
  times_used: number;
  buy_quantity: number | null;
  get_quantity: number | null;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
  applicable_to: string;
  description: string | null;
}

interface DiscountsResponse {
  discounts: Discount[];
}

interface DiscountResponse {
  discount: Discount;
  message: string;
}

interface ValidateDiscountResponse {
  valid: boolean;
  discount?: Discount;
  discount_amount?: number;
  final_amount?: number;
  message?: string;
}

@Injectable({
  providedIn: 'root',
})
export class DiscountService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getDiscounts(params?: any): Observable<DiscountsResponse> {
    return this.http.get<DiscountsResponse>(`${this.apiUrl}/discounts`, { params });
  }

  getDiscount(id: number): Observable<DiscountResponse> {
    return this.http.get<DiscountResponse>(`${this.apiUrl}/discounts/${id}`);
  }

  createDiscount(discount: any): Observable<DiscountResponse> {
    return this.http.post<DiscountResponse>(`${this.apiUrl}/discounts`, discount);
  }

  updateDiscount(id: number, discount: any): Observable<DiscountResponse> {
    return this.http.put<DiscountResponse>(`${this.apiUrl}/discounts/${id}`, discount);
  }

  deleteDiscount(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/discounts/${id}`);
  }

  validateDiscount(code: string, purchaseAmount: number): Observable<ValidateDiscountResponse> {
    return this.http.post<ValidateDiscountResponse>(`${this.apiUrl}/discounts/validate`, {
      code,
      purchase_amount: purchaseAmount,
    });
  }
}
