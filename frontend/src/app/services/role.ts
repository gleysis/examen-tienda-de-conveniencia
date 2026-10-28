import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Permission {
  id: number;
  name: string;
  slug: string;
  description: string | null;
}

export interface Role {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  permissions?: Permission[];
}

interface RolesResponse {
  roles: Role[];
}

interface RoleResponse {
  role: Role;
  message?: string;
}

interface PermissionsResponse {
  permissions: Permission[];
}

@Injectable({
  providedIn: 'root',
})
export class RoleService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getRoles(): Observable<RolesResponse> {
    return this.http.get<RolesResponse>(`${this.apiUrl}/roles-list`);
  }

  getPermissions(): Observable<PermissionsResponse> {
    return this.http.get<PermissionsResponse>(`${this.apiUrl}/permissions`);
  }

  createRole(role: any): Observable<RoleResponse> {
    return this.http.post<RoleResponse>(`${this.apiUrl}/roles-list`, role);
  }

  updateRole(id: number, role: any): Observable<RoleResponse> {
    return this.http.put<RoleResponse>(`${this.apiUrl}/roles-list/${id}`, role);
  }

  deleteRole(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/roles-list/${id}`);
  }

  syncPermissions(roleId: number, permissionIds: number[]): Observable<RoleResponse> {
    return this.http.post<RoleResponse>(`${this.apiUrl}/roles-list/${roleId}/sync-permissions`, {
      permissions: permissionIds,
    });
  }
}
