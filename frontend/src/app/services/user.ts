import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface Role {
  id: number;
  name: string;
  slug: string;
  description: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  roles: Role[];
}

interface UsersResponse {
  users: User[];
}

interface UserResponse {
  user: User;
  message: string;
}

interface RolesResponse {
  roles: Role[];
}

@Injectable({
  providedIn: 'root',
})
export class UserService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getUsers(): Observable<UsersResponse> {
    console.log('Fetching users from:', `${this.apiUrl}/users`);
    return this.http.get<UsersResponse>(`${this.apiUrl}/users`);
  }

  getUser(id: number): Observable<UserResponse> {
    return this.http.get<UserResponse>(`${this.apiUrl}/users/${id}`);
  }

  createUser(user: any): Observable<UserResponse> {
    return this.http.post<UserResponse>(`${this.apiUrl}/users`, user);
  }

  updateUser(id: number, user: any): Observable<UserResponse> {
    return this.http.put<UserResponse>(`${this.apiUrl}/users/${id}`, user);
  }

  deleteUser(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/users/${id}`);
  }

  getRoles(): Observable<RolesResponse> {
    return this.http.get<RolesResponse>(`${this.apiUrl}/roles`);
  }
}
