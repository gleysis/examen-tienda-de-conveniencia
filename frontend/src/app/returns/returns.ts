import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ReturnRefundService } from '../services/return-refund';
import { SaleService } from '../services/sale';
import type { Sale } from '../services/sale';
import { AuthService } from '../services/auth';
import { Layout } from '../layout/layout';
import { PaginationMeta } from '../services/pagination';
import { HasPermission } from '../directives/has-permission';
import { APP_PIPES } from '../pipes';

interface SaleItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  product?: { id: number; name: string };
}

interface ReturnRefund {
  id: number;
  return_number: string;
  sale_id: number;
  sale_item_id: number;
  product_id: number;
  user_id: number;
  quantity: number;
  refund_amount: number;
  reason: string;
  reason_description: string | null;
  status: string;
  refund_method: string;
  reference_number: string | null;
  processed_date: string | null;
  notes: string | null;
  sale?: { id: number; invoice_number: string };
  product?: { id: number; name: string };
  user?: { id: number; name: string };
}

@Component({
  imports: [CommonModule, FormsModule, Layout, HasPermission, ...APP_PIPES],
  selector: 'app-returns',
  styleUrl: './returns.css',
  templateUrl: './returns.html',
})
export class Returns implements OnInit {
  returns: ReturnRefund[] = [];
  sales: Sale[] = [];
  saleItems: SaleItem[] = [];
  meta: PaginationMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null };
  isLoading = false;
  errorMessage = '';
  showForm = false;
  isProcessingFormVisible = false;
  processingReturn: ReturnRefund | null = null;
  user: any;

  returnForm = {
    sale_id: null as number | null,
    sale_item_id: null as number | null,
    product_id: null as number | null,
    quantity: 1,
    refund_amount: 0,
    reason: 'other',
    reason_description: '',
    refund_method: 'cash',
    notes: '',
  };

  processForm = {
    reference_number: '',
  };

  searchQuery = '';
  statusFilter = '';

  constructor(
    private returnRefundService: ReturnRefundService,
    private saleService: SaleService,
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadReturns();
    this.loadSales();
  }

  loadReturns(page = 1): void {
    this.isLoading = true;
    this.errorMessage = '';
    const params: any = { page };
    if (this.searchQuery) params.search = this.searchQuery;
    if (this.statusFilter) params.status = this.statusFilter;

    this.returnRefundService.getReturns(params).subscribe({
      next: (response) => {
        this.returns = response.data || [];
        this.meta = response.meta;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading returns:', error);
        this.errorMessage = 'Error al cargar devoluciones';
        this.isLoading = false;
        this.cdr.detectChanges();
      },
    });
  }

  loadSales(): void {
    this.saleService.getSales({ status: 'completed', per_page: 100 }).subscribe({
      next: (response) => {
        this.sales = response.data || [];
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading sales:', error);
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadReturns(page);
    }
  }

  onFilter(): void {
    this.loadReturns();
  }

  showAddForm(): void {
    this.saleItems = [];
    this.returnForm = {
      sale_id: null,
      sale_item_id: null,
      product_id: null,
      quantity: 1,
      refund_amount: 0,
      reason: 'other',
      reason_description: '',
      refund_method: 'cash',
      notes: '',
    };
    this.errorMessage = '';
    this.showForm = true;
  }

  onSaleChange(): void {
    const sale = this.sales.find((s) => s.id === this.returnForm.sale_id);
    this.saleItems = (sale?.items ?? []) as SaleItem[];
    this.returnForm.sale_item_id = null;
    this.returnForm.product_id = null;
    this.returnForm.quantity = 1;
    this.returnForm.refund_amount = 0;
  }

  get selectedItem(): SaleItem | null {
    return this.saleItems.find((i) => i.id === this.returnForm.sale_item_id) ?? null;
  }

  onItemChange(): void {
    const item = this.selectedItem;
    if (!item) {
      this.returnForm.product_id = null;
      this.returnForm.refund_amount = 0;
      return;
    }
    this.returnForm.product_id = item.product_id;
    this.returnForm.quantity = Math.min(this.returnForm.quantity, item.quantity);
    this.updateRefundAmount();
  }

  onQuantityChange(): void {
    const item = this.selectedItem;
    if (item && this.returnForm.quantity > item.quantity) {
      this.returnForm.quantity = item.quantity;
    }
    this.updateRefundAmount();
  }

  updateRefundAmount(): void {
    const item = this.selectedItem;
    if (item) {
      const sale = this.sales.find((s) => s.id === this.returnForm.sale_id);
      const saleRatio = sale && Number(sale.subtotal) > 0 ? Number(sale.total) / Number(sale.subtotal) : 1;
      this.returnForm.refund_amount = Math.floor(this.returnForm.quantity * item.unit_price * saleRatio * 100) / 100;
    }
  }

  saveReturn(): void {
    if (!this.returnForm.sale_id || !this.returnForm.sale_item_id || !this.returnForm.product_id) {
      this.errorMessage = 'Selecciona una venta y un producto a devolver';
      return;
    }

    this.returnRefundService.createReturnRefund(this.returnForm).subscribe({
      next: () => {
        this.showForm = false;
        this.loadReturns();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error creating return:', error);
        this.errorMessage = error.error?.message || 'Error al crear devolución';
        this.cdr.detectChanges();
      },
    });
  }

  showProcessForm(returnRefund: ReturnRefund): void {
    this.processingReturn = returnRefund;
    this.processForm = {
      reference_number: '',
    };
    this.isProcessingFormVisible = true;
  }

  processReturn(): void {
    if (this.processingReturn) {
      this.returnRefundService.processReturnRefund(this.processingReturn.id, this.processForm).subscribe({
        next: () => {
          this.isProcessingFormVisible = false;
          this.processingReturn = null;
          this.loadReturns();
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error processing return:', error);
          this.errorMessage = error.error?.message || 'Error al procesar devolución';
          this.cdr.detectChanges();
        },
      });
    }
  }

  updateStatus(returnRefund: ReturnRefund, newStatus: string): void {
    this.returnRefundService.updateReturnRefund(returnRefund.id, { status: newStatus }).subscribe({
      next: () => {
        this.loadReturns();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error updating status:', error);
        this.errorMessage = error.error?.message || 'Error al actualizar estado';
        this.cdr.detectChanges();
      },
    });
  }

  deleteReturn(returnRefund: ReturnRefund): void {
    if (confirm(`¿Está seguro de eliminar la devolución ${returnRefund.return_number}?`)) {
      this.returnRefundService.deleteReturnRefund(returnRefund.id).subscribe({
        next: () => {
          this.loadReturns();
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error deleting return:', error);
          this.errorMessage = error.error?.message || 'Error al eliminar devolución';
          this.cdr.detectChanges();
        },
      });
    }
  }

  cancelForm(): void {
    this.showForm = false;
  }

  cancelProcessForm(): void {
    this.isProcessingFormVisible = false;
    this.processingReturn = null;
  }

  getStatusLabel(status: string): string {
    const labels: any = {
      pending: 'Pendiente',
      approved: 'Aprobado',
      rejected: 'Rechazado',
      processed: 'Procesado',
    };
    return labels[status] || status;
  }

  getReasonLabel(reason: string): string {
    const labels: any = {
      defective: 'Defectuoso',
      wrong_item: 'Producto incorrecto',
      no_longer_needed: 'Ya no se necesita',
      customer_preference: 'Preferencia del cliente',
      other: 'Otro',
    };
    return labels[reason] || reason;
  }

  getStatusClass(status: string): string {
    return `status-${status}`;
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/login']);
      },
      error: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        this.router.navigate(['/login']);
      },
    });
  }
}
