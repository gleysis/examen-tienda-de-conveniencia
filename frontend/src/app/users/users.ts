import { Component, OnInit, OnChanges, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { UserService } from '../services/user';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PaginationMeta } from '../services/pagination';

interface Role {
  id: number;
  name: string;
  slug: string;
  description: string;
  permissions?: Permission[];
}

interface Permission {
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

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-users',
  styleUrl: './users.css',
  templateUrl: './users.html',
})
export class Users implements OnInit, OnChanges, OnDestroy {
  users: User[] = [];
  filteredUsers: User[] = [];
  roles: Role[] = [];
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = true;
  errorMessage = '';
  showForm = false;
  isEditing = false;
  selectedUser: User | null = null;
  searchTerm = '';
  user: any;

  formData = {
    name: '',
    email: '',
    password: '',
    roles: [] as number[],
  };

  formErrors: any = {};

  constructor(
    private userService: UserService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    console.log('Users ngOnInit called');
    this.loadUsers();
    this.loadRoles();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/users' || event.urlAfterRedirects === '/users') {
        console.log('Navigated to users, reloading data');
        this.loadUsers();
        this.loadRoles();
      }
    });
  }

  ngOnDestroy(): void {
    console.log('Users ngOnDestroy called');
  }

  ngOnChanges(): void {
    this.filterUsers();
  }

  loadUsers(page = 1): void {
    this.isLoading = true;
    this.errorMessage = '';
    console.log('Cargando usuarios...');
    this.userService.getUsers({ page }).subscribe({
      next: (response) => {
        console.log('Usuarios cargados:', response);
        this.users = response.data || [];
        this.filteredUsers = this.users;
        this.meta = response.meta;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading users:', error);
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para ver usuarios.';
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al cargar usuarios';
        }
        this.isLoading = false;
        this.users = [];
        this.filteredUsers = [];
        this.cdr.detectChanges();
      },
    });
  }

  loadRoles(): void {
    console.log('Cargando roles...');
    this.userService.getRoles().subscribe({
      next: (response) => {
        console.log('Roles cargados:', response);
        this.roles = response.roles || [];
      },
      error: (error) => {
        console.error('Error loading roles:', error);
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para ver roles.';
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al cargar roles';
        }
        this.roles = [];
      },
    });
  }

  openCreateForm(): void {
    console.log('Abriendo formulario de creación');
    console.log('Roles disponibles:', this.roles);
    this.showForm = true;
    this.isEditing = false;
    this.selectedUser = null;
    this.formErrors = {};
    this.errorMessage = '';
    this.formData = {
      name: '',
      email: '',
      password: '',
      roles: [],
    };
  }

  openEditForm(user: User): void {
    this.showForm = true;
    this.isEditing = true;
    this.selectedUser = user;
    this.formErrors = {};
    this.errorMessage = '';
    this.formData = {
      name: user.name,
      email: user.email,
      password: '',
      roles: user.roles.map((r) => r.id),
    };
  }

  closeForm(): void {
    this.showForm = false;
    this.selectedUser = null;
  }

  onSubmit(): void {
    if (this.isEditing && this.selectedUser) {
      this.updateUser();
    } else {
      this.createUser();
    }
  }

  createUser(): void {
    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    this.userService.createUser(this.formData).subscribe({
      next: () => {
        this.loadUsers();
        this.closeForm();
        this.isLoading = false;
      },
      error: (error) => {
        this.isLoading = false;
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para crear usuarios.';
        } else if (error.status === 422) {
          if (error.error && error.error.errors) {
            this.formErrors = error.error.errors;
            this.errorMessage = 'Por favor corrige los errores del formulario';
          } else {
            this.errorMessage = 'Error de validación en el formulario';
          }
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al crear usuario';
        }
      },
    });
  }

  updateUser(): void {
    if (!this.selectedUser) return;

    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    const updateData: any = {
      name: this.formData.name,
      email: this.formData.email,
      roles: this.formData.roles,
    };

    if (this.formData.password) {
      updateData.password = this.formData.password;
    }

    this.userService.updateUser(this.selectedUser.id, updateData).subscribe({
      next: () => {
        this.loadUsers();
        this.closeForm();
        this.isLoading = false;
      },
      error: (error) => {
        this.isLoading = false;
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para actualizar usuarios.';
        } else if (error.status === 422) {
          if (error.error && error.error.errors) {
            this.formErrors = error.error.errors;
            this.errorMessage = 'Por favor corrige los errores del formulario';
          } else {
            this.errorMessage = 'Error de validación en el formulario';
          }
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al actualizar usuario';
        }
      },
    });
  }

  deleteUser(user: User): void {
    if (confirm(`¿Estás seguro de eliminar a ${user.name}?`)) {
      this.isLoading = true;
      this.userService.deleteUser(user.id).subscribe({
        next: () => {
          this.loadUsers();
          this.isLoading = false;
        },
        error: (error) => {
          this.errorMessage = 'Error al eliminar usuario';
          this.isLoading = false;
        },
      });
    }
  }

  toggleRole(roleId: number): void {
    const index = this.formData.roles.indexOf(roleId);
    if (index > -1) {
      this.formData.roles.splice(index, 1);
    } else {
      this.formData.roles.push(roleId);
    }
  }

  isRoleSelected(roleId: number): boolean {
    return this.formData.roles.includes(roleId);
  }

  getUserRoles(user: User): string {
    return user.roles.map((r) => r.name).join(', ');
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadUsers(page);
    }
  }

  filterUsers(): void {
    if (!this.searchTerm) {
      this.filteredUsers = this.users;
      return;
    }

    const term = this.searchTerm.toLowerCase();
    this.filteredUsers = this.users.filter(user =>
      user.name.toLowerCase().includes(term) ||
      user.email.toLowerCase().includes(term)
    );
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {
        window.location.href = '/login';
      },
      error: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.location.href = '/login';
      },
    });
  }
}
