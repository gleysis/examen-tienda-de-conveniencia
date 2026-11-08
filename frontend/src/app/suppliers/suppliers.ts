import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SupplierService } from '../services/supplier';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PaginationMeta } from '../services/pagination';
import { HasPermission } from '../directives/has-permission';
import { APP_PIPES } from '../pipes';

interface Supplier {
  id: number;
  name: string;
  contact_person: string | null;
  email: string | null;
  phone: string | null;
  tax_id: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  zip_code: string | null;
  country: string | null;
  payment_terms: number;
  credit_limit: number | null;
  current_balance: number;
  is_active: boolean;
  notes: string | null;
}

@Component({
  imports: [CommonModule, FormsModule, Layout, HasPermission, ...APP_PIPES],
  selector: 'app-suppliers',
  styleUrl: './suppliers.css',
  templateUrl: './suppliers.html',
})
export class Suppliers implements OnInit {
  suppliers: Supplier[] = [];
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = false;
  errorMessage = '';
  showForm = false;
  editingSupplier: Supplier | null = null;
  user: any;

  supplierForm = {
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    tax_id: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    country: '',
    payment_terms: 30,
    credit_limit: null as number | null,
    current_balance: 0,
    is_active: true,
    notes: '',
  };

  searchQuery = '';

  constructor(
    private supplierService: SupplierService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadSuppliers();
  }

  loadSuppliers(page = 1): void {
    this.isLoading = true;
    const params: any = { page };
    if (this.searchQuery) params.search = this.searchQuery;

    console.log('Loading suppliers with params:', params);
    this.supplierService.getSuppliers(params).subscribe({
      next: (response) => {
        console.log('Suppliers loaded:', response);
        this.suppliers = response.data || [];
        this.meta = response.meta;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading suppliers:', error);
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para ver proveedores.';
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al cargar proveedores';
        }
        this.isLoading = false;
        this.suppliers = [];
        this.cdr.detectChanges();
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadSuppliers(page);
    }
  }

  onSearch(): void {
    this.loadSuppliers();
  }

  showAddForm(): void {
    this.editingSupplier = null;
    this.supplierForm = {
      name: '',
      contact_person: '',
      email: '',
      phone: '',
      tax_id: '',
      address: '',
      city: '',
      state: '',
      zip_code: '',
      country: '',
      payment_terms: 30,
      credit_limit: null as number | null,
      current_balance: 0,
      is_active: true,
      notes: '',
    };
    this.showForm = true;
  }

  editSupplier(supplier: Supplier): void {
    this.editingSupplier = supplier;
    this.supplierForm = {
      name: supplier.name,
      contact_person: supplier.contact_person || '',
      email: supplier.email || '',
      phone: supplier.phone || '',
      tax_id: supplier.tax_id || '',
      address: supplier.address || '',
      city: supplier.city || '',
      state: supplier.state || '',
      zip_code: supplier.zip_code || '',
      country: supplier.country || '',
      payment_terms: supplier.payment_terms,
      credit_limit: supplier.credit_limit,
      current_balance: supplier.current_balance,
      is_active: supplier.is_active,
      notes: supplier.notes || '',
    };
    this.showForm = true;
  }

  saveSupplier(): void {
    if (this.editingSupplier) {
      this.supplierService.updateSupplier(this.editingSupplier.id, this.supplierForm).subscribe({
        next: () => {
          this.showForm = false;
          this.loadSuppliers();
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error updating supplier:', error);
          this.errorMessage = error.error?.message || 'Error al actualizar proveedor';
          this.cdr.detectChanges();
        },
      });
    } else {
      this.supplierService.createSupplier(this.supplierForm).subscribe({
        next: () => {
          this.showForm = false;
          this.loadSuppliers();
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error creating supplier:', error);
          this.errorMessage = error.error?.message || 'Error al crear proveedor';
          this.cdr.detectChanges();
        },
      });
    }
  }

  deleteSupplier(supplier: Supplier): void {
    if (confirm(`¿Está seguro de eliminar a ${supplier.name}?`)) {
      this.supplierService.deleteSupplier(supplier.id).subscribe({
        next: () => {
          this.loadSuppliers();
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error deleting supplier:', error);
          this.errorMessage = error.error?.message || 'Error al eliminar proveedor';
          this.cdr.detectChanges();
        },
      });
    }
  }

  cancelForm(): void {
    this.showForm = false;
    this.editingSupplier = null;
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
