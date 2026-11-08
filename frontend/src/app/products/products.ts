import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { ProductService } from '../services/product';
import { CategoryService } from '../services/category';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PaginationMeta } from '../services/pagination';
import { HasPermission } from '../directives/has-permission';
import { DateInput } from '../directives/date-input';
import { APP_PIPES } from '../pipes';

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
  expiry_date: string | null;
  category_id: number;
  category: Category;
  barcode: string;
  image: string;
  is_active: boolean;
}

@Component({
  imports: [CommonModule, FormsModule, DateInput, Layout, HasPermission, ...APP_PIPES],
  selector: 'app-products',
  styleUrl: './products.css',
  templateUrl: './products.html',
})
export class Products implements OnInit, OnDestroy {
  products: Product[] = [];
  categories: Category[] = [];
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = false;
  errorMessage = '';
  showForm = false;
  isEditing = false;
  selectedProduct: Product | null = null;
  searchQuery = '';
  selectedCategoryId: number | null = null;
  user: any;

  formData = {
    name: '',
    sku: '',
    description: '',
    price: 0,
    cost: 0,
    stock: 0,
    min_stock: 0,
    max_stock: null as number | null,
    expiry_date: '',
    category_id: null as number | null,
    barcode: '',
    image: '',
    is_active: true,
  };

  formErrors: any = {};
  selectedImageFile: File | null = null;
  imagePreview: string | null = null;

  constructor(
    private productService: ProductService,
    private categoryService: CategoryService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    console.log('Products ngOnInit called');
    this.loadProducts();
    this.loadCategories();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/products' || event.urlAfterRedirects === '/products') {
        console.log('Navigated to products, reloading data');
        this.loadProducts();
        this.loadCategories();
      }
    });
  }

  ngOnDestroy(): void {
    console.log('Products ngOnDestroy called');
  }

  loadProducts(page = 1): void {
    this.isLoading = true;
    this.errorMessage = '';
    const params: any = { page };
    if (this.searchQuery) params.search = this.searchQuery;
    if (this.selectedCategoryId) params.category_id = this.selectedCategoryId;

    console.log('Loading products with params:', params);
    this.productService.getProducts(params).subscribe({
      next: (response) => {
        console.log('Products loaded:', response);
        if (!response.data?.length && page > 1 && response.meta?.last_page < page) {
          this.loadProducts(response.meta.last_page);
          return;
        }
        this.products = response.data || [];
        this.meta = response.meta;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading products:', error);
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para ver productos.';
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al cargar productos';
        }
        this.isLoading = false;
        this.products = [];
        this.cdr.detectChanges();
      },
    });
  }

  loadCategories(): void {
    console.log('Loading categories');
    this.categoryService.getCategories().subscribe({
      next: (response) => {
        console.log('Categories loaded:', response);
        this.categories = response.categories || [];
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading categories:', error);
        if (error.status === 401) {
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.errorMessage = 'No tienes permiso para ver categorías.';
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.errorMessage = error.error?.message || 'Error al cargar categorías';
        }
        this.categories = [];
        this.cdr.detectChanges();
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadProducts(page);
    }
  }

  onSearch(): void {
    this.loadProducts();
  }

  onCategoryChange(): void {
    this.loadProducts();
  }

  openCreateForm(): void {
    this.showForm = true;
    this.isEditing = false;
    this.selectedProduct = null;
    this.formErrors = {};
    this.errorMessage = '';
    this.selectedImageFile = null;
    this.imagePreview = null;
    this.formData = {
      name: '',
      sku: '',
      description: '',
      price: 0,
      cost: 0,
      stock: 0,
      min_stock: 0,
      max_stock: null,
      expiry_date: '',
      category_id: null,
      barcode: '',
      image: '',
      is_active: true,
    };
  }

  openEditForm(product: Product): void {
    this.showForm = true;
    this.isEditing = true;
    this.selectedProduct = product;
    this.formErrors = {};
    this.errorMessage = '';
    this.selectedImageFile = null;
    this.imagePreview = product.image || null;
    this.formData = {
      name: product.name,
      sku: product.sku,
      description: product.description,
      price: product.price,
      cost: product.cost,
      stock: product.stock,
      min_stock: product.min_stock,
      max_stock: product.max_stock || null,
      expiry_date: product.expiry_date || '',
      category_id: product.category_id || null,
      barcode: product.barcode,
      image: product.image,
      is_active: product.is_active,
    };
  }

  closeForm(): void {
    this.showForm = false;
    this.selectedProduct = null;
  }

  onImageSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.selectedImageFile = input.files[0];
      this.imagePreview = URL.createObjectURL(this.selectedImageFile);
      this.cdr.detectChanges();
    }
  }

  private buildFormData(): FormData {
    const data = new FormData();
    data.append('name', this.formData.name);
    data.append('sku', this.formData.sku);
    data.append('description', this.formData.description || '');
    data.append('price', String(this.formData.price));
    data.append('cost', this.formData.cost !== null ? String(this.formData.cost) : '');
    data.append('stock', String(this.formData.stock));
    data.append('min_stock', String(this.formData.min_stock));
    if (this.formData.max_stock !== null) {
      data.append('max_stock', String(this.formData.max_stock));
    }
    data.append('expiry_date', this.formData.expiry_date || '');
    data.append('category_id', String(this.formData.category_id));
    data.append('barcode', this.formData.barcode || '');
    data.append('image', this.formData.image || '');
    data.append('is_active', this.formData.is_active ? '1' : '0');
    if (this.selectedImageFile) {
      data.append('image_file', this.selectedImageFile);
    }
    return data;
  }

  onSubmit(): void {
    if (this.isEditing && this.selectedProduct) {
      this.updateProduct();
    } else {
      this.createProduct();
    }
  }

  createProduct(): void {
    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    this.productService.createProductFormData(this.buildFormData()).subscribe({
      next: () => {
        this.loadProducts();
        this.closeForm();
        this.isLoading = false;
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.errors) {
          this.formErrors = error.error.errors;
          this.errorMessage = 'Por favor corrige los errores del formulario';
        } else {
          this.errorMessage = error.error?.message || 'Error al crear producto';
        }
      },
    });
  }

  updateProduct(): void {
    if (!this.selectedProduct) return;

    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    this.productService.updateProductFormData(this.selectedProduct.id, this.buildFormData()).subscribe({
      next: () => {
        this.loadProducts(this.meta.current_page);
        this.closeForm();
        this.isLoading = false;
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.errors) {
          this.formErrors = error.error.errors;
          this.errorMessage = 'Por favor corrige los errores del formulario';
        } else {
          this.errorMessage = error.error?.message || 'Error al actualizar producto';
        }
      },
    });
  }

  deleteProduct(product: Product): void {
    if (confirm(`¿Estás seguro de eliminar el producto ${product.name}?`)) {
      this.isLoading = true;
      this.productService.deleteProduct(product.id).subscribe({
        next: () => {
          this.loadProducts(this.meta.current_page);
          this.isLoading = false;
        },
        error: (error) => {
          this.errorMessage = 'Error al eliminar producto';
          this.isLoading = false;
        },
      });
    }
  }

  isLowStock(product: Product): boolean {
    return product.stock <= product.min_stock;
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
