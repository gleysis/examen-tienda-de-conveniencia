<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function sales(Request $request): StreamedResponse
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

        return $this->csvResponse('ventas.csv', [
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

    public function inventoryMovements(Request $request): StreamedResponse
    {
        $query = InventoryMovement::with(['product', 'user'])->latest();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->csvResponse('movimientos-inventario.csv', [
            'Fecha', 'Producto', 'SKU', 'Tipo', 'Cantidad',
            'Stock anterior', 'Stock nuevo', 'Referencia', 'Usuario', 'Notas',
        ], $query->cursor()->map(fn (InventoryMovement $movement) => [
            $movement->created_at?->format('Y-m-d H:i'),
            $movement->product?->name,
            $movement->product?->sku,
            $movement->type === 'entry' ? 'Entrada' : 'Salida',
            $movement->quantity,
            $movement->previous_stock,
            $movement->new_stock,
            $movement->reference,
            $movement->user?->name,
            $movement->notes,
        ]));
    }

    public function inventoryStock(Request $request): StreamedResponse
    {
        $query = Product::with('category')->where('is_active', true)->orderBy('name');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return $this->csvResponse('inventario-stock.csv', [
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
