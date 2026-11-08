<?php

namespace App\Http\Controllers\Api;

use App\Concerns\WritesXlsx;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    use WritesXlsx;

    public function sales(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = Sale::with('user')->latest();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $this->exportResponse($request, 'ventas', [
            'Factura', 'Fecha', 'Vendedor', 'Subtotal', 'Impuesto', 'Descuento',
            'Total', 'Método de pago', 'Estado',
        ], $query->cursor()->map(fn (Sale $sale) => [
            $sale->invoice_number,
            $sale->created_at?->format('Y-m-d H:i'),
            $sale->user?->name,
            $sale->subtotal,
            $sale->tax,
            $sale->discount,
            $sale->total,
            $sale->payment_method,
            $sale->status,
        ]));
    }

    public function inventoryMovements(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = InventoryMovement::with(['product', 'user'])->latest();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('product')) {
            $search = '%'.$request->product.'%';
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', $search)
                ->orWhere('sku', 'like', $search)->orWhere('barcode', 'like', $search));
        }

        if ($request->filled('user')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$request->user.'%'));
        }

        return $this->exportResponse($request, 'movimientos-inventario', [
            'Fecha', 'Producto', 'SKU', 'Tipo', 'Cantidad',
            'Stock anterior', 'Stock nuevo', 'Usuario', 'Referencia', 'Notas',
        ], $query->cursor()->map(fn (InventoryMovement $movement) => [
            $movement->created_at?->format('Y-m-d H:i'),
            $movement->product?->name,
            $movement->product?->sku,
            $movement->type === 'entry' ? 'Entrada' : 'Salida',
            $movement->quantity,
            $movement->previous_stock,
            $movement->new_stock,
            $movement->user?->name,
            $movement->reference,
            $movement->notes,
        ]));
    }

    public function inventoryStock(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = Product::with('category')->where('is_active', true)->orderBy('name');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)
                ->orWhere('sku', 'like', $search)->orWhere('barcode', 'like', $search));
        }

        if ($request->filled('status')) {
            if ($request->status === 'low') {
                $query->whereNotNull('min_stock')->whereColumn('stock', '<=', 'min_stock');
            } elseif ($request->status === 'over') {
                $query->whereNotNull('max_stock')->whereColumn('stock', '>=', 'max_stock');
            } elseif ($request->status === 'ok') {
                $query->where(fn ($q) => $q->whereNull('min_stock')->orWhereColumn('stock', '>', 'min_stock'))
                    ->where(fn ($q) => $q->whereNull('max_stock')->orWhereColumn('stock', '<', 'max_stock'));
            }
        }

        return $this->exportResponse($request, 'inventario-stock', [
            'SKU', 'Producto', 'Categoría', 'Stock', 'Stock mínimo',
            'Costo', 'Precio', 'Valor en stock',
        ], $query->cursor()->map(fn (Product $product) => [
            $product->sku,
            $product->name,
            $product->category?->name,
            $product->stock,
            $product->min_stock,
            $product->cost,
            $product->price,
            $product->stock * $product->cost,
        ]));
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function exportResponse(Request $request, string $baseName, array $headers, iterable $rows): StreamedResponse|BinaryFileResponse
    {
        if ($request->input('format') === 'xlsx') {
            return $this->xlsxResponse($baseName.'.xlsx', $headers, $rows);
        }

        return $this->csvResponse($baseName.'.csv', $headers, $rows);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function csvResponse(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens accented characters correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
