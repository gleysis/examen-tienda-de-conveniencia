import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { ProductService } from '../services/product';
import { SaleService } from '../services/sale';
import { DiscountService } from '../services/discount';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';

interface Product {
  id: number;
  name: string;
  sku: string;
  price: number;
  stock: number;
  category: any;
}

interface CartItem {
  product: Product;
  quantity: number;
  unit_price: number;
  subtotal: number;
}

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-pos',
  styleUrl: './pos.css',
  templateUrl: './pos.html',
})
export class Pos implements OnInit, OnDestroy {
  products: Product[] = [];
  cart: CartItem[] = [];
  searchQuery = '';
  isLoading = false;
  errorMessage = '';
  isProcessingSale = false;
  user: any;

  discountCode = '';
  discountAmount = 0;
  discountValid = false;

  saleData = {
    payment_method: 'cash',
    discount: 0,
    tax: 0,
    notes: '',
  };

  amountReceived: number = 0;
  change: number = 0;

  constructor(
    private productService: ProductService,
    private saleService: SaleService,
    private discountService: DiscountService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    console.log('POS ngOnInit called');
    this.loadProducts();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/pos' || event.urlAfterRedirects === '/pos') {
        console.log('Navigated to POS, reloading products');
        this.loadProducts();
      }
    });
  }

  ngOnDestroy(): void {
    console.log('POS ngOnDestroy called');
  }

  loadProducts(): void {
    this.isLoading = true;
    this.errorMessage = '';
    console.log('Loading POS products');
    this.productService.getProducts({ is_active: true }).subscribe({
      next: (response) => {
        console.log('POS products loaded:', response);
        this.products = response.products || [];
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

  onSearch(): void {
    if (this.searchQuery) {
      console.log('Searching products:', this.searchQuery);
      this.productService.getProducts({ search: this.searchQuery }).subscribe({
        next: (response) => {
          console.log('Search results:', response);
          this.products = response.products || [];
        },
        error: (error) => {
          console.error('Error searching products:', error);
          this.errorMessage = error.error?.message || 'Error al buscar productos';
        },
      });
    } else {
      this.loadProducts();
    }
  }

  addToCart(product: Product): void {
    if (product.stock <= 0) {
      this.errorMessage = 'No hay suficientes unidades disponibles de este producto';
      return;
    }

    const existingItem = this.cart.find((item) => item.product.id === product.id);

    if (existingItem) {
      if (existingItem.quantity >= product.stock) {
        this.errorMessage = 'No hay suficiente stock disponible. Stock actual: ' + product.stock;
        return;
      }
      existingItem.quantity++;
      existingItem.subtotal = existingItem.quantity * existingItem.unit_price;
      this.errorMessage = 'Producto agregado correctamente';
    } else {
      this.cart.push({
        product,
        quantity: 1,
        unit_price: product.price,
        subtotal: product.price,
      });
      this.errorMessage = 'Producto agregado correctamente';
    }
    
    this.cdr.detectChanges();
    
    setTimeout(() => {
      this.errorMessage = '';
      this.cdr.detectChanges();
    }, 3000);
  }

  removeFromCart(index: number): void {
    if (confirm('¿Deseas eliminar este producto de la venta?')) {
      this.cart.splice(index, 1);
      this.errorMessage = 'Producto eliminado de la venta';
      this.cdr.detectChanges();
      
      setTimeout(() => {
        this.errorMessage = '';
        this.cdr.detectChanges();
      }, 3000);
    }
  }

  updateQuantity(item: CartItem, change: number): void {
    const newQuantity = item.quantity + change;

    if (newQuantity <= 0) {
      const index = this.cart.indexOf(item);
      this.cart.splice(index, 1);
      return;
    }

    if (newQuantity > item.product.stock) {
      this.errorMessage = 'No hay suficiente stock disponible';
      return;
    }

    item.quantity = newQuantity;
    item.subtotal = item.quantity * item.unit_price;
    this.errorMessage = '';
  }

  get subtotal(): number {
    return this.cart.reduce((sum, item) => sum + item.subtotal, 0);
  }

  get total(): number {
    return this.subtotal - this.saleData.discount + this.saleData.tax;
  }

  calculateChange(): void {
    if (this.amountReceived >= this.total) {
      this.change = this.amountReceived - this.total;
    } else {
      this.change = 0;
    }
  }

  clearCart(): void {
    if (this.cart.length > 0) {
      if (confirm('¿Estás seguro de cancelar la venta actual? Todos los productos serán eliminados del carrito.')) {
        this.cart = [];
        this.saleData = {
          payment_method: 'cash',
          discount: 0,
          tax: 0,
          notes: '',
        };
        this.discountCode = '';
        this.discountAmount = 0;
        this.discountValid = false;
        this.amountReceived = 0;
        this.change = 0;
        this.errorMessage = 'Venta cancelada';
        this.cdr.detectChanges();
        
        setTimeout(() => {
          this.errorMessage = '';
          this.cdr.detectChanges();
        }, 3000);
      }
    }
  }

  validateDiscountCode(): void {
    if (!this.discountCode) {
      this.discountAmount = 0;
      this.discountValid = false;
      return;
    }

    this.discountService.validateDiscount(this.discountCode, this.subtotal).subscribe({
      next: (response) => {
        if (response.valid) {
          this.discountAmount = response.discount_amount || 0;
          this.discountValid = true;
          this.saleData.discount = this.discountAmount;
          this.errorMessage = '';
        } else {
          this.discountAmount = 0;
          this.discountValid = false;
          this.saleData.discount = 0;
          this.errorMessage = response.message || 'Código de descuento inválido';
        }
      },
      error: (error) => {
        console.error('Error validating discount:', error);
        this.discountAmount = 0;
        this.discountValid = false;
        this.saleData.discount = 0;
        this.errorMessage = 'Error al validar código de descuento';
      },
    });
  }

  processSale(): void {
    if (this.cart.length === 0) {
      this.errorMessage = 'El carrito está vacío. Agrega productos antes de procesar la venta.';
      return;
    }

    if (this.saleData.payment_method === 'cash' && this.amountReceived < this.total) {
      this.errorMessage = 'Pago insuficiente. El monto recibido debe cubrir el total de la compra.';
      return;
    }

    if (this.saleData.payment_method === 'cash' && this.amountReceived >= this.total) {
      if (!confirm('¿Estás seguro de confirmar el pago?')) {
        return;
      }
    }

    this.isProcessingSale = true;
    this.errorMessage = '';

    const salePayload: any = {
      items: this.cart.map((item) => ({
        product_id: item.product.id,
        quantity: item.quantity,
        unit_price: item.unit_price,
      })),
      payment_method: this.saleData.payment_method,
      discount: this.saleData.discount,
      tax: this.saleData.tax,
      notes: this.saleData.notes,
    };

    if (this.discountCode && this.discountValid) {
      salePayload.discount_code = this.discountCode;
    }

    this.saleService.createSale(salePayload).subscribe({
      next: (response) => {
        if (response.sale) {
          alert('Venta completada exitosamente.\nFactura: ' + response.sale.invoice_number + '\nTotal: $' + this.total.toFixed(2));
          this.clearCart();
          this.loadProducts();
        } else if (response.error) {
          this.errorMessage = response.error;
        }
        this.isProcessingSale = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error processing sale:', error);
        this.errorMessage = error.error?.message || 'Error al procesar la venta';
        this.isProcessingSale = false;
        this.cdr.detectChanges();
      },
    });
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
