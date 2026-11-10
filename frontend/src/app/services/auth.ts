import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';

interface Permission {
  id: number;
  name: string;
  slug: string;
}

interface Role {
  id: number;
  name: string;
  slug: string;
  permissions?: Permission[];
}

interface AuthUser {
  id: number;
  name: string;
  email: string;
  roles?: Role[];
}

interface LoginResponse {
  user: AuthUser;
  token: string;
}

interface UserResponse {
  user: AuthUser;
}

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  login(email: string, password: string): Observable<LoginResponse> {
    console.log('AuthService - Attempting login for:', email);
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, { email, password }).pipe(
      map((response) => {
        console.log('AuthService - Login successful');
        console.log('AuthService - Token received:', response.token ? response.token.substring(0, 20) + '...' : 'none');
        localStorage.setItem('auth_token', response.token);
        localStorage.setItem('user', JSON.stringify(response.user));
        console.log('AuthService - Token saved to localStorage');
        console.log('AuthService - User saved to localStorage:', response.user);
        return response;
      }),
      catchError((error) => {
        console.error('AuthService - Login error:', error);
        return throwError(() => error);
      })
    );
  }

  logout(): Observable<{ message: string }> {
    console.log('AuthService - Attempting logout');
    return this.http.post<{ message: string }>(`${this.apiUrl}/logout`, {}).pipe(
      map(() => {
        console.log('AuthService - Logout successful');
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        return { message: 'Successfully logged out' };
      }),
      catchError((error) => {
        console.error('AuthService - Logout error:', error);
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        return throwError(() => error);
      })
    );
  }

  changePassword(currentPassword: string, password: string, passwordConfirmation: string): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/me/password`, {
      current_password: currentPassword,
      password,
      password_confirmation: passwordConfirmation,
    });
  }

  getUser(): Observable<UserResponse> {
    console.log('AuthService - Getting current user');
    return this.http.get<UserResponse>(`${this.apiUrl}/user`).pipe(
      map((response) => {
        localStorage.setItem('user', JSON.stringify(response.user));
        return response;
      }),
      catchError((error) => {
        console.error('AuthService - Get user error:', error);
        return throwError(() => error);
      })
    );
  }

  isAuthenticated(): boolean {
    const token = localStorage.getItem('auth_token');
    return !!token;
  }

  getCurrentUser(): any {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
  }

  getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  getPermissions(): string[] {
    const user = this.getCurrentUser();
    if (!user?.roles) {
      return [];
    }
    const slugs = new Set<string>();
    for (const role of user.roles) {
      for (const permission of role.permissions ?? []) {
        slugs.add(permission.slug);
      }
    }
    return [...slugs];
  }

  hasPermission(permission: string): boolean {
    return this.getPermissions().includes(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some((permission) => this.hasPermission(permission));
  }

  hasPermissionsLoaded(): boolean {
    const user = this.getCurrentUser();
    return !!user?.roles?.some((role: Role) => Array.isArray(role.permissions));
  }
}
