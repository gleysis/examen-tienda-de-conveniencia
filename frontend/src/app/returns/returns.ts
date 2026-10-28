import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReturnRefundService } from '../services/return-refund';
import { SaleService } from '../services/sale';

interface ReturnRefund {
  id: number;
  return_number: string;
  sale_id: number;
  sale_item_id: number;
  product_id: number;
  customer_id: number | null;
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
}

interface Sale {
  id: number;
  invoice_number: string;
  items: any[];
}

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-returns',
  styleUrl: './returns.css',
  templateUrl: './returns.html',
})
export class Returns implements OnInit {
  returns: ReturnRefund[] = [];
  sales: Sale[] = [];
  isLoading = false;
  errorMessage = '';
  showForm = false;
  isProcessingFormVisible = false;
  editingReturn: ReturnRefund | null = null;
  processingReturn: ReturnRefund | null = null;

  returnForm = {
    sale_id: null as number | null,
    sale_item_id: null as number | null,
    product_id: null as number | null,
    customer_id: null as number | null,
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
    private saleService: SaleService
  ) {}

  ngOnInit(): void {
    this.loadReturns();
    this.loadSales();
  }

  loadReturns(): void {
    this.isLoading = true;
    const params: any = {};
    if (this.searchQuery) params.search = this.searchQuery;
    if (this.statusFilter) params.status = this.statusFilter;

    this.returnRefundService.getReturns(params).subscribe({
      next: (response) => {
        this.returns = response.returns;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading returns:', error);
        this.errorMessage = 'Error al cargar devoluciones';
        this.isLoading = false;
      },
    });
  }

  loadSales(): void {
    this.saleService.getSales({ status: 'completed' }).subscribe({
      next: (response) => {
        this.sales = response.sales;
      },
      error: (error) => {
        console.error('Error loading sales:', error);
      },
    });
  }

  onFilter(): void {
    this.loadReturns();
  }

  showAddForm(): void {
    this.editingReturn = null;
    this.returnForm = {
      sale_id: null,
      sale_item_id: null,
      product_id: null,
      customer_id: null,
      quantity: 1,
      refund_amount: 0,
      reason: 'other',
      reason_description: '',
      refund_method: 'cash',
      notes: '',
    };
    this.showForm = true;
  }

  onSaleChange(): void {
    const sale = this.sales.find((s) => s.id === this.returnForm.sale_id);
    if (sale) {
      this.returnForm.customer_id = (sale as any).customer_id;
    }
  }

  saveReturn(): void {
    this.returnRefundService.createReturnRefund(this.returnForm).subscribe({
      next: (response) => {
        alert('Devolución creada exitosamente');
        this.showForm = false;
        this.loadReturns();
      },
      error: (error) => {
        console.error('Error creating return:', error);
        this.errorMessage = error.error?.message || 'Error al crear devolución';
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
        next: (response) => {
          alert('Devolución procesada exitosamente');
          this.isProcessingFormVisible = false;
          this.processingReturn = null;
          this.loadReturns();
        },
        error: (error) => {
          console.error('Error processing return:', error);
          this.errorMessage = error.error?.message || 'Error al procesar devolución';
        },
      });
    }
  }

  updateStatus(returnRefund: ReturnRefund, newStatus: string): void {
    this.returnRefundService.updateReturnRefund(returnRefund.id, { status: newStatus }).subscribe({
      next: () => {
        alert('Estado actualizado exitosamente');
        this.loadReturns();
      },
      error: (error) => {
        console.error('Error updating status:', error);
        this.errorMessage = error.error?.message || 'Error al actualizar estado';
      },
    });
  }

  deleteReturn(returnRefund: ReturnRefund): void {
    if (confirm(`¿Está seguro de eliminar la devolución ${returnRefund.return_number}?`)) {
      this.returnRefundService.deleteReturnRefund(returnRefund.id).subscribe({
        next: () => {
          alert('Devolución eliminada exitosamente');
          this.loadReturns();
        },
        error: (error) => {
          console.error('Error deleting return:', error);
          this.errorMessage = error.error?.message || 'Error al eliminar devolución';
        },
      });
    }
  }

  cancelForm(): void {
    this.showForm = false;
    this.editingReturn = null;
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

  getStatusClass(status: string): string {
    return `status-${status}`;
  }
}
