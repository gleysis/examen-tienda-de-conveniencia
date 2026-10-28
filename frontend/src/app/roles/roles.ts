import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RoleService, Role, Permission } from '../services/role';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-roles',
  styleUrl: './roles.css',
  templateUrl: './roles.html',
})
export class Roles implements OnInit {
  roles: Role[] = [];
  permissions: Permission[] = [];
  permissionGroups: { name: string; permissions: Permission[] }[] = [];

  isLoading = false;
  errorMessage = '';
  user: any;
  formErrors: any = {};

  showForm = false;
  editingRole: Role | null = null;
  roleForm = { name: '', slug: '', description: '' };

  showPermissions = false;
  selectedRole: Role | null = null;
  selectedPermissionIds: Set<number> = new Set();
  isSavingPermissions = false;

  constructor(
    private roleService: RoleService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadRoles();
    this.loadPermissions();
  }

  loadRoles(): void {
    this.isLoading = true;
    this.roleService.getRoles().subscribe({
      next: (response) => {
        this.roles = response.roles || [];
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading roles:', error);
        this.errorMessage = error.error?.message || 'Error al cargar roles';
        this.isLoading = false;
        this.cdr.detectChanges();
      },
    });
  }

  loadPermissions(): void {
    this.roleService.getPermissions().subscribe({
      next: (response) => {
        this.permissions = response.permissions || [];
        this.permissionGroups = this.groupPermissions(this.permissions);
        this.cdr.detectChanges();
      },
      error: (error) => console.error('Error loading permissions:', error),
    });
  }

  private groupPermissions(permissions: Permission[]): { name: string; permissions: Permission[] }[] {
    const groups = new Map<string, Permission[]>();
    for (const permission of permissions) {
      const groupName = permission.slug.split('.')[0] || 'otros';
      if (!groups.has(groupName)) {
        groups.set(groupName, []);
      }
      groups.get(groupName)!.push(permission);
    }
    return [...groups.entries()].map(([name, perms]) => ({ name, permissions: perms }));
  }

  showAddForm(): void {
    this.editingRole = null;
    this.roleForm = { name: '', slug: '', description: '' };
    this.formErrors = {};
    this.showForm = true;
  }

  editRole(role: Role): void {
    this.editingRole = role;
    this.roleForm = { name: role.name, slug: role.slug, description: role.description || '' };
    this.formErrors = {};
    this.showForm = true;
  }

  saveRole(): void {
    this.formErrors = {};
    this.errorMessage = '';
    const request = this.editingRole
      ? this.roleService.updateRole(this.editingRole.id, this.roleForm)
      : this.roleService.createRole(this.roleForm);

    request.subscribe({
      next: () => {
        this.showForm = false;
        this.loadRoles();
      },
      error: (error) => {
        if (error.status === 422 && error.error?.errors) {
          this.formErrors = error.error.errors;
        } else {
          this.errorMessage = error.error?.message || 'Error al guardar el rol';
        }
        this.cdr.detectChanges();
      },
    });
  }

  deleteRole(role: Role): void {
    if (!confirm(`¿Eliminar el rol "${role.name}"?`)) {
      return;
    }

    this.roleService.deleteRole(role.id).subscribe({
      next: () => this.loadRoles(),
      error: (error) => {
        this.errorMessage = error.error?.message || 'Error al eliminar el rol';
        this.cdr.detectChanges();
      },
    });
  }

  cancelForm(): void {
    this.showForm = false;
    this.editingRole = null;
  }

  openPermissions(role: Role): void {
    this.selectedRole = role;
    this.selectedPermissionIds = new Set((role.permissions || []).map((p) => p.id));
    this.showPermissions = true;
  }

  isPermissionSelected(id: number): boolean {
    return this.selectedPermissionIds.has(id);
  }

  togglePermission(id: number): void {
    if (this.selectedPermissionIds.has(id)) {
      this.selectedPermissionIds.delete(id);
    } else {
      this.selectedPermissionIds.add(id);
    }
  }

  toggleGroup(group: { name: string; permissions: Permission[] }): void {
    const allSelected = group.permissions.every((p) => this.selectedPermissionIds.has(p.id));
    for (const permission of group.permissions) {
      if (allSelected) {
        this.selectedPermissionIds.delete(permission.id);
      } else {
        this.selectedPermissionIds.add(permission.id);
      }
    }
  }

  isGroupSelected(group: { name: string; permissions: Permission[] }): boolean {
    return group.permissions.length > 0 && group.permissions.every((p) => this.selectedPermissionIds.has(p.id));
  }

  savePermissions(): void {
    if (!this.selectedRole) {
      return;
    }

    this.isSavingPermissions = true;
    this.errorMessage = '';
    this.roleService
      .syncPermissions(this.selectedRole.id, [...this.selectedPermissionIds])
      .subscribe({
        next: () => {
          this.isSavingPermissions = false;
          this.showPermissions = false;
          this.selectedRole = null;
          this.loadRoles();
        },
        error: (error) => {
          this.isSavingPermissions = false;
          this.errorMessage = error.error?.message || 'Error al guardar los permisos';
          this.cdr.detectChanges();
        },
      });
  }

  cancelPermissions(): void {
    this.showPermissions = false;
    this.selectedRole = null;
  }
}
