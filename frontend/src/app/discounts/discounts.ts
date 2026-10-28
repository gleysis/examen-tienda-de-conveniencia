import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DiscountService } from '../services/discount';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';
import { PaginationMeta } from '../services/pagination';

interface Discount {
  id: number;
  code: string;
  name: string;
  type: string;
  value: number;
  minimum_purchase: number | null;
  maximum_discount: number | null;
  usage_limit: number | null;
  times_used: number;
  buy_quantity: number | null;
  get_quantity: number | null;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
  applicable_to: string;
  description: string | null;
}

@Component({
  imports: [CommonModule, FormsModule, Layout],
  selector: 'app-discounts',
  styleUrl: './discounts.css',
  templateUrl: './discounts.html',
})
export class Discounts implements OnInit {
  discounts: Discount[] = [];
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = false;
  errorMessage = '';
  showForm = false;
  editingDiscount: Discount | null = null;
  user: any;
  formErrors: any = {};

  discountForm = {
    code: '',
    name: '',
    type: 'percentage',
    value: 0,
    minimum_purchase: null as number | null,
    maximum_discount: null as number | null,
    usage_limit: null as number | null,
    buy_quantity: null as number | null,
    get_quantity: null as number | null,
    start_date: '',
    end_date: '',
    is_active: true,
    applicable_to: 'all_products',
    description: '',
  };

  searchQuery = '';

  constructor(private discountService: DiscountService, private authService: AuthService) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadDiscounts();
  }

  loadDiscounts(page = 1): void {
    this.isLoading = true;
    this.errorMessage = '';
    const params: any = { page };
    if (this.searchQuery) params.search = this.searchQuery;

    this.discountService.getDiscounts(params).subscribe({
      next: (response) => {
        this.discounts = response.data || [];
        this.meta = response.meta;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading discounts:', error);
        this.errorMessage = error.error?.message || 'Error al cargar descuentos';
        this.isLoading = false;
        this.discounts = [];
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadDiscounts(page);
    }
  }

  onSearch(): void {
    this.loadDiscounts();
  }

  showAddForm(): void {
    this.editingDiscount = null;
    this.discountForm = {
      code: '',
      name: '',
      type: 'percentage',
      value: 0,
      minimum_purchase: null,
      maximum_discount: null,
      usage_limit: null,
      buy_quantity: null,
      get_quantity: null,
      start_date: '',
      end_date: '',
      is_active: true,
      applicable_to: 'all_products',
      description: '',
    };
    this.formErrors = {};
    this.showForm = true;
  }

  editDiscount(discount: Discount): void {
    this.editingDiscount = discount;
    this.discountForm = {
      code: discount.code,
      name: discount.name,
      type: discount.type,
      value: discount.value,
      minimum_purchase: discount.minimum_purchase,
      maximum_discount: discount.maximum_discount,
      usage_limit: discount.usage_limit,
      buy_quantity: discount.buy_quantity,
      get_quantity: discount.get_quantity,
      start_date: discount.start_date || '',
      end_date: discount.end_date || '',
      is_active: discount.is_active,
      applicable_to: discount.applicable_to,
      description: discount.description || '',
    };
    this.formErrors = {};
    this.showForm = true;
  }

  saveDiscount(): void {
    this.formErrors = {};
    this.errorMessage = '';
    const payload: any = { ...this.discountForm };
    if (!payload.start_date) delete payload.start_date;
    if (!payload.end_date) delete payload.end_date;
    if (!payload.description) delete payload.description;

    const request = this.editingDiscount
      ? this.discountService.updateDiscount(this.editingDiscount.id, payload)
      : this.discountService.createDiscount(payload);

    request.subscribe({
      next: () => {
        this.showForm = false;
        this.loadDiscounts(this.meta.current_page);
      },
      error: (error) => {
        console.error('Error saving discount:', error);
        if (error.status === 422 && error.error?.errors) {
          this.formErrors = error.error.errors;
        } else {
          this.errorMessage = error.error?.message || 'Error al guardar el descuento';
        }
      },
    });
  }

  deleteDiscount(discount: Discount): void {
    if (!confirm(`¿Eliminar el descuento "${discount.name}"?`)) {
      return;
    }

    this.discountService.deleteDiscount(discount.id).subscribe({
      next: () => this.loadDiscounts(this.meta.current_page),
      error: (error) => {
        console.error('Error deleting discount:', error);
        this.errorMessage = error.error?.message || 'Error al eliminar el descuento';
      },
    });
  }

  cancelForm(): void {
    this.showForm = false;
    this.editingDiscount = null;
  }

  getTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      percentage: 'Porcentaje',
      fixed_amount: 'Monto fijo',
      buy_x_get_y: 'Compra X lleva Y',
      free_shipping: 'Envío gratis',
    };
    return labels[type] || type;
  }

  getApplicableToLabel(applicableTo: string): string {
    const labels: Record<string, string> = {
      all_products: 'Todos los productos',
      specific_products: 'Productos específicos',
      categories: 'Categorías',
    };
    return labels[applicableTo] || applicableTo;
  }

  formatValue(discount: Discount): string {
    if (discount.type === 'percentage') {
      return `${discount.value}%`;
    }
    if (discount.type === 'buy_x_get_y') {
      return `${discount.buy_quantity}x${discount.get_quantity}`;
    }
    return `$${Number(discount.value).toFixed(2)}`;
  }
}
