import { Component, OnInit, ChangeDetectorRef, OnDestroy, HostListener, ViewChild, ElementRef, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { ProductService } from '../services/product';
import { SaleService } from '../services/sale';
import { DiscountService } from '../services/discount';
import { CategoryService } from '../services/category';
import { SystemSettingService } from '../services/system-setting';
import { NotificationService } from '../services/notification';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { APP_PIPES } from '../pipes';
import { tr } from '../services/i18n';
import { ReceiptSettings, TicketPrinterService } from '../services/ticket-printer';
import { AppDatePipe } from '../pipes/app-date';

interface Product {
  id: number;
  name: string;
  sku: string;
  price: number;
  stock: number;
  image?: string;
  barcode?: string;
  category_id?: number;
  category: any;
}

interface CartItem {
  product: Product;
  quantity: number;
  unit_price: number;
  subtotal: number;
}

@Component({
  imports: [CommonModule, FormsModule, Layout, ...APP_PIPES],
  selector: 'app-pos',
  styleUrl: './pos.css',
  templateUrl: './pos.html',
})
export class Pos implements OnInit, OnDestroy, AfterViewInit {
  products: Product[] = [];
  allProducts: Product[] = [];
  categories: any[] = [];
  selectedCategoryId: number | null = null;
  cart: CartItem[] = [];
  searchQuery = '';
  scannedCode = '';
  isLoading = false;
  errorMessage = '';
  isProcessingSale = false;
  user: any;
  taxRate = 0.15;
  showCheckout = false;
  messageType: 'success' | 'error' = 'error';

  @ViewChild('searchInput') searchInputRef?: ElementRef<HTMLInputElement>;
  private scanBuffer = '';
  private lastKeyTime = 0;
  private readonly SCAN_INTERVAL_MS = 70;
  private readonly SCAN_MIN_LENGTH = 4;

  discountCode = '';
  discountAmount = 0;
  discountValid = false;

  saleData = {
    payment_method: 'cash',
    discount: 0,
    notes: '',
  };

  amountReceived: number = 0;
  change: number = 0;

  showReceipt = false;
  receipt: {
    invoice_number: string;
    created_at: string;
    cashier: string;
    items: { name: string; quantity: number; unit_price: number; subtotal: number }[];
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
    payment_method: string;
    amount_received: number;
    change: number;
  } | null = null;

  storeInfo = {
    name: 'Tienda Leiva',
    address: '',
    phone: '',
    email: '',
    taxId: '',
  };
  currencySymbol = 'C$';
  receiptFooter = '¡Gracias por su compra!';
  autoPrintReceipt = false;
  allowNegativeStock = false;
  allowDiscounts = true;
  maxDiscountPercent = 100;
  storeLogo = '';
  receiptShowLogo = true;
  receiptShowStoreInfo = true;
  confirmCancelSale = true;
  receiptSettings: ReceiptSettings = TicketPrinterService.toReceiptSettings({});
  private readonly datePipe = new AppDatePipe();

  constructor(
    private ticketPrinter: TicketPrinterService,
    private productService: ProductService,
    private saleService: SaleService,
    private discountService: DiscountService,
    private categoryService: CategoryService,
    private systemSettingService: SystemSettingService,
    private notificationService: NotificationService,
    private authService: AuthService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    console.log('POS ngOnInit called');
    this.loadProducts();
    this.loadCategories();
    this.loadStoreInfo();

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

  loadStoreInfo(): void {
    this.systemSettingService.getPublicSettings().subscribe({
      next: (response) => {
        const settings = response.settings || {};
        this.storeInfo = {
          name: settings['store_name'] || 'Tienda Leiva',
          address: settings['store_address'] || '',
          phone: settings['store_phone'] || '',
          email: settings['store_email'] || '',
          taxId: settings['store_tax_id'] || '',
        };
        const rawTaxRate = Number(settings['tax_rate']);
        if (settings['tax_rate'] !== undefined && settings['tax_rate'] !== null && Number.isFinite(rawTaxRate)) {
          this.taxRate = rawTaxRate / 100;
        }
        this.currencySymbol = settings['currency_symbol'] || 'C$';
        this.receiptFooter = settings['receipt_footer'] || '¡Gracias por su compra!';
        this.autoPrintReceipt = this.toBoolean(settings['auto_print_receipt']);
        this.allowNegativeStock = this.toBoolean(settings['allow_negative_stock']);
        this.allowDiscounts = settings['allow_discounts'] === undefined || this.toBoolean(settings['allow_discounts']);
        const rawMaxDiscount = Number(settings['max_discount_percent']);
        this.maxDiscountPercent = Number.isFinite(rawMaxDiscount) ? rawMaxDiscount : 100;
        this.storeLogo = settings['store_logo'] || '';
        this.receiptShowLogo = settings['receipt_show_logo'] === undefined || this.toBoolean(settings['receipt_show_logo']);
        this.receiptShowStoreInfo =
          settings['receipt_show_store_info'] === undefined || this.toBoolean(settings['receipt_show_store_info']);
        this.confirmCancelSale =
          settings['confirm_cancel_sale'] === undefined || this.toBoolean(settings['confirm_cancel_sale']);
        this.receiptSettings = TicketPrinterService.toReceiptSettings(settings);
        this.cdr.detectChanges();
      },
      error: () => {
        // Keep default store info if public settings are unavailable.
      },
    });
  }

  private toBoolean(value: unknown): boolean {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  private toAmount(value: unknown, fallback: number): number {
    const amount = Number(value);
    return Number.isFinite(amount) ? amount : fallback;
  }

  /** A validated discount depends on the subtotal, so any cart change requires re-validation. */
  private invalidateDiscount(): void {
    if (!this.discountValid && this.saleData.discount === 0) {
      return;
    }
    this.discountAmount = 0;
    this.discountValid = false;
    this.saleData.discount = 0;
    this.notificationService.show('El carrito cambió. Vuelve a aplicar el código de descuento.', 'info');
  }

  get taxRatePercent(): number {
    return parseFloat((this.taxRate * 100).toFixed(2));
  }

  loadProducts(): void {
    this.isLoading = true;
    this.messageType = 'error';
    this.errorMessage = '';
    console.log('Loading POS products');
    this.productService.getProducts({ is_active: true, per_page: 100 }).subscribe({
      next: (response) => {
        console.log('POS products loaded:', response);
        this.allProducts = (response.data || []).map((p: Product) => ({
          ...p,
          price: Number(p.price) || 0,
        }));
        this.applyFilters();
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading products:', error);
        if (error.status === 401) {
          this.messageType = 'error';
          this.errorMessage = 'No estás autenticado. Por favor inicia sesión.';
        } else if (error.status === 403) {
          this.messageType = 'error';
          this.errorMessage = 'No tienes permiso para ver productos.';
        } else if (error.status === 0) {
          this.messageType = 'error';
          this.errorMessage = 'No se puede conectar con el servidor. Verifica que el backend esté ejecutándose.';
        } else {
          this.messageType = 'error';
          this.errorMessage = error.error?.message || 'Error al cargar productos';
        }
        this.isLoading = false;
        this.products = [];
        this.cdr.detectChanges();
      },
    });
  }

  loadCategories(): void {
    this.categoryService.getCategories().subscribe({
      next: (response) => {
        this.categories = response.categories || [];
        this.cdr.detectChanges();
      },
      error: () => {
        this.categories = [];
      },
    });
  }

  selectCategory(categoryId: number | null): void {
    this.selectedCategoryId = categoryId;
    this.applyFilters();
    this.cdr.detectChanges();
  }

  applyFilters(): void {
    const query = (this.searchQuery.trim() || this.scannedCode).toLowerCase();
    this.products = this.allProducts.filter(
      (p) =>
        (this.selectedCategoryId === null ||
          p.category?.id === this.selectedCategoryId ||
          p.category_id === this.selectedCategoryId) &&
        (!query ||
          p.name.toLowerCase().includes(query) ||
          p.sku.toLowerCase().includes(query) ||
          p.barcode?.toLowerCase().includes(query))
    );
  }

  onSearchInput(): void {
    this.scannedCode = '';
    this.applyFilters();
    this.cdr.detectChanges();
  }

  onSearch(): void {
    this.scannedCode = '';
    if (this.searchQuery) {
      console.log('Searching products:', this.searchQuery);
      this.productService.getProducts({ search: this.searchQuery, per_page: 100 }).subscribe({
        next: (response) => {
          console.log('Search results:', response);
          const results = (response.data || []).map((p: Product) => ({
            ...p,
            price: Number(p.price) || 0,
          }));
          if (results.length === 1) {
            // Exact match (e.g. barcode scan): add straight to the cart.
            this.addToCart(results[0]);
            if (!this.allProducts.some((p) => p.id === results[0].id)) {
              this.allProducts = [...this.allProducts, results[0]];
            }
            this.showScannedProduct(this.searchQuery.trim());
            return;
          }
          this.products = results;
        },
        error: (error) => {
          console.error('Error searching products:', error);
          this.messageType = 'error';
          this.errorMessage = error.error?.message || 'Error al buscar productos';
        },
      });
    } else {
      this.loadProducts();
    }
  }

  addToCart(product: Product): void {
    if (!this.allowNegativeStock && product.stock <= 0) {
      this.messageType = 'error';
      this.errorMessage = 'No hay suficientes unidades disponibles de este producto';
      return;
    }

    const existingItem = this.cart.find((item) => item.product.id === product.id);

    if (existingItem) {
      if (!this.allowNegativeStock && existingItem.quantity >= product.stock) {
        this.messageType = 'error';
        this.errorMessage = 'No hay suficiente stock disponible. Stock actual: ' + product.stock;
        return;
      }
      existingItem.quantity++;
      existingItem.subtotal = existingItem.quantity * existingItem.unit_price;
      this.invalidateDiscount();
      this.messageType = 'success';
      this.errorMessage = 'Producto agregado correctamente';
    } else {
      const price = Number(product.price) || 0;
      this.cart.push({
        product,
        quantity: 1,
        unit_price: price,
        subtotal: price,
      });
      this.invalidateDiscount();
      this.messageType = 'success';
      this.errorMessage = 'Producto agregado correctamente';
    }
    
    this.cdr.detectChanges();
    
    setTimeout(() => {
      this.messageType = 'error';
      this.errorMessage = '';
      this.cdr.detectChanges();
    }, 3000);
  }

  removeFromCart(index: number): void {
    if (confirm('¿Deseas eliminar este producto de la venta?')) {
      this.cart.splice(index, 1);
      this.invalidateDiscount();
      this.messageType = 'success';
      this.errorMessage = 'Producto eliminado de la venta';
      this.cdr.detectChanges();
      
      setTimeout(() => {
        this.messageType = 'error';
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
      this.invalidateDiscount();
      return;
    }

    if (!this.allowNegativeStock && newQuantity > item.product.stock) {
      this.messageType = 'error';
      this.errorMessage = 'No hay suficiente stock disponible';
      return;
    }

    item.quantity = newQuantity;
    item.subtotal = item.quantity * item.unit_price;
    this.invalidateDiscount();
    this.messageType = 'error';
    this.errorMessage = '';
  }

  get subtotal(): number {
    return this.cart.reduce((sum, item) => sum + item.subtotal, 0);
  }

  get taxAmount(): number {
    return this.subtotal * this.taxRate;
  }

  get total(): number {
    return this.subtotal - this.saleData.discount + this.taxAmount;
  }

  ngAfterViewInit(): void {
    this.focusSearch();
  }

  @HostListener('window:keydown', ['$event'])
  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'F5') {
      event.preventDefault();
      this.openCheckout();
      return;
    }

    if (this.showCheckout || this.showReceipt) {
      return;
    }

    const target = event.target as HTMLElement;
    const isSearchInput = target === this.searchInputRef?.nativeElement;
    const isOtherField =
      !isSearchInput && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName);

    if (isOtherField) {
      this.scanBuffer = '';
      return;
    }

    const now = Date.now();

    if (event.key === 'Enter') {
      if (this.scanBuffer.length >= this.SCAN_MIN_LENGTH) {
        event.preventDefault();
        const code = this.scanBuffer;
        this.scanBuffer = '';
        this.handleScan(code);
      } else {
        this.scanBuffer = '';
        if (isSearchInput) {
          this.onSearch();
        }
      }
      return;
    }

    if (event.key.length === 1) {
      this.scanBuffer =
        now - this.lastKeyTime > this.SCAN_INTERVAL_MS
          ? event.key
          : this.scanBuffer + event.key;
      this.lastKeyTime = now;
    }
  }

  handleScan(code: string): void {
    const clean = code.trim();
    if (!clean || this.isLoading) {
      return;
    }

    const found = this.allProducts.find(
      (p) => p.barcode === clean || p.sku.toLowerCase() === clean.toLowerCase()
    );

    if (found) {
      this.showScannedProduct(clean);
      this.addToCart(found);
      this.focusSearch();
      return;
    }

    this.productService.getProducts({ search: clean, per_page: 10 }).subscribe({
      next: (response) => {
        const match = (response.data || [])
          .map((p: Product) => ({ ...p, price: Number(p.price) || 0 }))
          .find(
            (p) =>
              p.barcode === clean || p.sku.toLowerCase() === clean.toLowerCase()
          );

        if (match) {
          if (!this.allProducts.some((p) => p.id === match.id)) {
            this.allProducts = [...this.allProducts, match];
          }
          this.showScannedProduct(clean);
          this.addToCart(match);
        } else {
          this.clearScanInput();
          this.messageType = 'error';
          this.errorMessage = `Producto no registrado: ${clean}`;
          setTimeout(() => {
            this.messageType = 'error';
            this.errorMessage = '';
            this.cdr.detectChanges();
          }, 3000);
        }

        this.focusSearch();
        this.cdr.detectChanges();
      },
      error: () => {
        this.messageType = 'error';
        this.errorMessage = 'Error al buscar el producto';
        this.focusSearch();
        this.cdr.detectChanges();
      },
    });
  }

  private clearScanInput(): void {
    const input = this.searchInputRef?.nativeElement;
    if (input) {
      input.value = '';
    }
    this.searchQuery = '';
    this.scannedCode = '';
    this.applyFilters();
  }

  private showScannedProduct(code: string): void {
    const input = this.searchInputRef?.nativeElement;
    if (input) {
      input.value = '';
    }
    this.searchQuery = '';
    this.scannedCode = code;
    this.selectedCategoryId = null;
    this.applyFilters();
  }

  focusSearch(): void {
    if (this.showCheckout || this.showReceipt) {
      return;
    }
    setTimeout(() => this.searchInputRef?.nativeElement.focus(), 0);
  }

  openCheckout(): void {
    if (this.cart.length === 0) {
      this.messageType = 'error';
      this.errorMessage = 'El carrito está vacío. Agrega productos antes de cobrar.';
      setTimeout(() => {
        this.messageType = 'error';
        this.errorMessage = '';
        this.cdr.detectChanges();
      }, 3000);
      return;
    }
    this.errorMessage = '';
    this.showCheckout = true;
    this.cdr.detectChanges();
  }

  closeCheckout(): void {
    this.showCheckout = false;
    this.errorMessage = '';
  }

  calculateChange(): void {
    this.change = this.cashChange;
  }

  get cashChange(): number {
    return Math.max(0, this.toCents(this.amountReceivedValue - this.total) / 100);
  }

  get amountReceivedValue(): number {
    return this.toAmount(this.amountReceived, 0);
  }

  get cashShortfall(): number {
    return Math.max(0, this.toCents(this.total - this.amountReceivedValue) / 100);
  }

  /** Reason the cash payment cannot be confirmed yet, or null when it is valid. */
  get cashPaymentError(): string | null {
    if (this.saleData.payment_method !== 'cash') {
      return null;
    }
    if (this.amountReceivedValue < 0) {
      return 'El dinero recibido no puede ser negativo.';
    }
    if (this.amountReceivedValue === 0) {
      return 'Ingresa el dinero recibido del cliente para continuar.';
    }
    if (this.cashShortfall > 0) {
      return 'Monto insuficiente. Faltan {amount} para completar el pago.';
    }
    return null;
  }

  private toCents(amount: number): number {
    return Math.round(amount * 100);
  }

  clearCart(): void {
    if (this.cart.length > 0) {
      if (
        !this.confirmCancelSale ||
        confirm('¿Estás seguro de cancelar la venta actual? Todos los productos serán eliminados del carrito.')
      ) {
        this.resetSaleState();
        this.messageType = 'error';
        this.errorMessage = 'Venta cancelada';
        this.cdr.detectChanges();

        setTimeout(() => {
          this.messageType = 'error';
          this.errorMessage = '';
          this.cdr.detectChanges();
        }, 3000);
      }
    }
  }

  private resetSaleState(): void {
    this.cart = [];
    this.scannedCode = '';
    this.searchQuery = '';
    this.applyFilters();
    this.saleData = {
      payment_method: 'cash',
      discount: 0,
      notes: '',
    };
    this.discountCode = '';
    this.discountAmount = 0;
    this.discountValid = false;
    this.amountReceived = 0;
    this.change = 0;
  }

  printReceipt(): void {
    if (!this.receipt) {
      return;
    }

    this.ticketPrinter.print(
      {
        ...this.receipt,
        date: this.datePipe.transform(this.receipt.created_at, 'datetime'),
      },
      this.receiptSettings
    );
  }

  closeReceipt(): void {
    this.showReceipt = false;
    this.receipt = null;
  }

  getPaymentMethodLabel(method: string): string {
    const labels: Record<string, string> = {
      cash: 'Efectivo',
      card: 'Tarjeta',
      transfer: 'Transferencia',
    };
    return labels[method] || method;
  }

  get maxDiscountAmount(): number {
    return Math.round(this.subtotal * this.maxDiscountPercent) / 100;
  }

  validateDiscountCode(): void {
    if (!this.allowDiscounts || !this.discountCode) {
      this.discountAmount = 0;
      this.discountValid = false;
      this.saleData.discount = 0;
      return;
    }

    this.discountService.validateDiscount(this.discountCode, this.subtotal).subscribe({
      next: (response) => {
        if (response.valid && (response.discount_amount || 0) > this.maxDiscountAmount + 0.001) {
          this.discountAmount = 0;
          this.discountValid = false;
          this.saleData.discount = 0;
          this.messageType = 'error';
          this.errorMessage = `El descuento supera el máximo permitido (${this.maxDiscountPercent}% del subtotal).`;
        } else if (response.valid) {
          this.discountAmount = response.discount_amount || 0;
          this.discountValid = true;
          this.saleData.discount = this.discountAmount;
          this.messageType = 'error';
          this.errorMessage = '';
        } else {
          this.discountAmount = 0;
          this.discountValid = false;
          this.saleData.discount = 0;
          this.messageType = 'error';
          this.errorMessage = response.message || 'Código de descuento inválido';
        }
      },
      error: (error) => {
        console.error('Error validating discount:', error);
        this.discountAmount = 0;
        this.discountValid = false;
        this.saleData.discount = 0;
        this.messageType = 'error';
        this.errorMessage = 'Error al validar código de descuento';
      },
    });
  }

  processSale(): void {
    if (this.cart.length === 0) {
      this.messageType = 'error';
      this.errorMessage = 'El carrito está vacío. Agrega productos antes de procesar la venta.';
      return;
    }

    if (this.cashPaymentError) {
      return;
    }

    if (this.saleData.payment_method === 'cash' && !confirm(tr('¿Estás seguro de confirmar el pago?'))) {
      return;
    }

    this.isProcessingSale = true;
    this.messageType = 'error';
    this.errorMessage = '';

    const salePayload: any = {
      items: this.cart.map((item) => ({
        product_id: item.product.id,
        quantity: item.quantity,
        unit_price: item.unit_price,
      })),
      payment_method: this.saleData.payment_method,
      amount_received: this.saleData.payment_method === 'cash' ? this.amountReceivedValue : null,
      discount: 0,
      tax: this.taxAmount,
      notes: this.saleData.notes,
    };

    // The backend computes the code discount itself; sending the amount too would apply it twice.
    if (this.allowDiscounts && this.discountCode && this.discountValid) {
      salePayload.discount_code = this.discountCode;
    }

    this.saleService.createSale(salePayload).subscribe({
      next: (response) => {
        if (response.sale) {
          this.receipt = {
            invoice_number: response.sale.invoice_number,
            created_at: response.sale.created_at || new Date().toISOString(),
            cashier: response.sale.user?.name || this.user?.name || '',
            items: this.cart.map((item) => ({
              name: item.product.name,
              quantity: item.quantity,
              unit_price: item.unit_price,
              subtotal: item.subtotal,
            })),
            // Totals come from the server, which is the source of truth for discounts.
            subtotal: this.toAmount(response.sale.subtotal, this.subtotal),
            discount: this.toAmount(response.sale.discount, this.saleData.discount),
            tax: this.toAmount(response.sale.tax, this.taxAmount),
            total: this.toAmount(response.sale.total, this.total),
            payment_method: this.saleData.payment_method,
            amount_received: this.amountReceived,
            change: this.change,
          };
          this.showReceipt = true;
          this.showCheckout = false;
          this.resetSaleState();
          this.loadProducts();
          if (this.autoPrintReceipt) {
            setTimeout(() => this.printReceipt(), 150);
          }
          const depleted: string[] = response.out_of_stock_products || [];
          if (depleted.length) {
            this.notificationService.show(`Producto agotado: ${depleted.join(', ')}`, 'info', 8000);
          }
        } else if (response.error) {
          this.messageType = 'error';
          this.errorMessage = response.error;
        }
        this.isProcessingSale = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error processing sale:', error);
        this.messageType = 'error';
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
