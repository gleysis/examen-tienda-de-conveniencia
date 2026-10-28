import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string;
}

interface Product {
  id: number;
  name: string;
  sku: string;
  description: string;
  price: number;
  cost: number;
  stock: number;
  min_stock: number;
  max_stock: number;
  category_id: number;
  category: Category;
  barcode: string;
  image: string;
  is_active: boolean;
}

interface ProductsResponse {
  products: Product[];
}

interface ProductResponse {
  product: Product;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class ProductService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getProducts(params?: any): Observable<ProductsResponse> {
    return this.http.get<ProductsResponse>(`${this.apiUrl}/products`, { params });
  }

  getProduct(id: number): Observable<ProductResponse> {
    return this.http.get<ProductResponse>(`${this.apiUrl}/products/${id}`);
  }

  createProduct(product: any): Observable<ProductResponse> {
    return this.http.post<ProductResponse>(`${this.apiUrl}/products`, product);
  }

  updateProduct(id: number, product: any): Observable<ProductResponse> {
    return this.http.put<ProductResponse>(`${this.apiUrl}/products/${id}`, product);
  }

  deleteProduct(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/products/${id}`);
  }
}
