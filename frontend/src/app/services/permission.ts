import { Injectable } from '@angular/core';
import { AuthService } from './auth';

@Injectable({
  providedIn: 'root',
})
export class PermissionService {
  private userPermissions: string[] = [];

  constructor(private authService: AuthService) {
    this.loadUserPermissions();
  }

  loadUserPermissions(): void {
    const user = this.authService.getCurrentUser();
    if (user && user.roles) {
      this.userPermissions = this.extractPermissions(user.roles);
      console.log('User permissions loaded:', this.userPermissions);
    }
  }

  private extractPermissions(roles: any[]): string[] {
    const permissions: string[] = [];
    roles.forEach((role) => {
      if (role.permissions) {
        role.permissions.forEach((perm: any) => {
          if (!permissions.includes(perm.slug)) {
            permissions.push(perm.slug);
          }
        });
      }
    });
    return permissions;
  }

  hasPermission(permission: string): boolean {
    return this.userPermissions.includes(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some((perm) => this.userPermissions.includes(perm));
  }

  hasAllPermissions(permissions: string[]): boolean {
    return permissions.every((perm) => this.userPermissions.includes(perm));
  }

  hasRole(role: string): boolean {
    const user = this.authService.getCurrentUser();
    if (user && user.roles) {
      return user.roles.some((r: any) => r.slug === role);
    }
    return false;
  }

  refreshPermissions(): void {
    this.loadUserPermissions();
  }
}