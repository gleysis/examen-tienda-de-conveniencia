import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string;
  is_active: boolean;
}

interface CategoriesResponse {
  categories: Category[];
}

interface CategoryResponse {
  category: Category;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class CategoryService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getCategories(): Observable<CategoriesResponse> {
    return this.http.get<CategoriesResponse>(`${this.apiUrl}/categories`);
  }

  getCategory(id: number): Observable<CategoryResponse> {
    return this.http.get<CategoryResponse>(`${this.apiUrl}/categories/${id}`);
  }

  createCategory(category: any): Observable<CategoryResponse> {
    return this.http.post<CategoryResponse>(`${this.apiUrl}/categories`, category);
  }

  updateCategory(id: number, category: any): Observable<CategoryResponse> {
    return this.http.put<CategoryResponse>(`${this.apiUrl}/categories/${id}`, category);
  }

  deleteCategory(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/categories/${id}`);
  }
}
