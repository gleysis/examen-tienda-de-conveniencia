import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { InventoryService } from '../services/inventory';
import { ProductService } from '../services/product';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';

interface InventoryMovement {
  id: number;
  product_id: number;
  user_id: number;
  type: string;
  quantity: number;
  previous_stock: number;
  new_stock: number;
  reference: string;
  notes: string;
  product: any;
  user: any;
  created_at: string;
}

interface Product {
  id: number;
  name: string;
  sku: string;
  stock: number;
  category?: any;
  min_stock?: number;
  price?: number;
}

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-inventory',
  styleUrl: './inventory.css',
  templateUrl: './inventory.html',
})
export class Inventory implements OnInit, OnDestroy {
  movements: InventoryMovement[] = [];
  products: Product[] = [];
  lowStockProducts: Product[] = [];
  isLoading = false;
  errorMessage = '';
  showForm = false;
  activeTab = 'movements';
  user: any;

  formData = {
    product_id: null,
    type: 'entry',
    quantity: 1,
    reference: '',
    notes: '',
  };

  formErrors: any = {};

  reportSummary: any = null;

  constructor(
    private inventoryService: InventoryService,
    private productService: ProductService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    console.log('Inventory ngOnInit called');
    this.loadMovements();
    this.loadProducts();
    this.loadLowStock();
    this.loadReport();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/inventory' || event.urlAfterRedirects === '/inventory') {
        console.log('Navigated to inventory, reloading data');
        this.loadMovements();
        this.loadProducts();
        this.loadLowStock();
        this.loadReport();
      }
    });
  }

  ngOnDestroy(): void {
    console.log('Inventory ngOnDestroy called');
  }

  loadMovements(): void {
    this.isLoading = true;
    this.inventoryService.getMovements().subscribe({
      next: (response) => {
        this.movements = response.movements as any;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading movements:', error);
        this.errorMessage = 'Error al cargar movimientos';
        this.isLoading = false;
        this.cdr.detectChanges();
      },
    });
  }

  loadProducts(): void {
    this.productService.getProducts().subscribe({
      next: (response) => {
        this.products = response.products;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading products:', error);
        this.cdr.detectChanges();
      },
    });
  }

  loadLowStock(): void {
    this.inventoryService.getLowStock().subscribe({
      next: (response) => {
        this.lowStockProducts = response.products;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading low stock:', error);
        this.cdr.detectChanges();
      },
    });
  }

  loadReport(): void {
    this.inventoryService.getReport().subscribe({
      next: (response) => {
        this.reportSummary = response.summary;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading report:', error);
        this.cdr.detectChanges();
      },
    });
  }

  openForm(): void {
    this.showForm = true;
    this.formErrors = {};
    this.errorMessage = '';
    this.formData = {
      product_id: null,
      type: 'entry',
      quantity: 1,
      reference: '',
      notes: '',
    };
  }

  closeForm(): void {
    this.showForm = false;
  }

  onSubmit(): void {
    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    this.inventoryService.createMovement(this.formData).subscribe({
      next: (response) => {
        if (response.movement) {
          this.loadMovements();
          this.loadLowStock();
          this.loadReport();
          this.loadProducts();
          this.closeForm();
          this.isLoading = false;
        } else if (response.error) {
          this.errorMessage = response.error;
          this.isLoading = false;
        }
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.errors) {
          this.formErrors = error.error.errors;
          this.errorMessage = 'Por favor corrige los errores del formulario';
        } else {
          this.errorMessage = error.error?.message || 'Error al registrar movimiento';
        }
      },
    });
  }

  setTab(tab: string): void {
    this.activeTab = tab;
  }

  getMovementTypeLabel(type: string): string {
    return type === 'entry' ? 'Entrada' : 'Salida';
  }

  getMovementTypeClass(type: string): string {
    return type === 'entry' ? 'entry' : 'exit';
  }

  isLowStock(product: Product): boolean {
    return product.stock <= (product.min_stock || 0);
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
