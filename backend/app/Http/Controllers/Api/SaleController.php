<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\CashRegisterSession;
use App\Models\Discount;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Sale::with(['user', 'cashRegister', 'items.product']);

        if ($request->user()->isLimitedToOwnRecords()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sales = $query->latest()->paginate($request->integer('per_page', 15));

        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $session = CashRegisterSession::where('user_id', auth()->id())->open()->lockForUpdate()->first();

            if (! $session) {
                abort(422, 'No tienes una caja abierta. Abre tu caja antes de registrar ventas.');
            }

            $subtotal = 0;
            $saleItems = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if (! SystemSettings::value('allow_negative_stock', false) && $product->stock < $item['quantity']) {
                    abort(422, "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, Solicitado: {$item['quantity']}");
                }

                $unitPrice = (float) $product->price;
                $itemSubtotal = round($item['quantity'] * $unitPrice, 2);
                $subtotal += $itemSubtotal;

                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $discount = 0;

            if (! SystemSettings::value('allow_discounts', true) && $request->discount_code) {
                abort(422, 'Los descuentos en ventas están deshabilitados.');
            }

            if ($request->discount_code) {
                $discountObj = Discount::where('code', $request->discount_code)->first();
                if ($discountObj && $discountObj->isValid()) {
                    $discountAmount = match ($discountObj->type) {
                        'percentage' => $subtotal * ($discountObj->value / 100),
                        'fixed_amount' => $discountObj->value,
                        default => 0,
                    };

                    if ($discountObj->maximum_discount) {
                        $discountAmount = min($discountAmount, $discountObj->maximum_discount);
                    }

                    $discount += $discountAmount;
                    $discountObj->incrementUsage();
                }
            }

            $maxDiscountPercent = (float) SystemSettings::value('max_discount_percent', 100);
            if ($discount > 0 && $discount > round($subtotal * $maxDiscountPercent / 100, 2)) {
                abort(422, "El descuento supera el máximo permitido ({$maxDiscountPercent}% del subtotal).");
            }

            $discount = round($discount, 2);
            $tax = round($subtotal * (float) SystemSettings::value('tax_rate', 0) / 100, 2);
            $total = round($subtotal - $discount + $tax, 2);

            if ($request->payment_method === 'cash' && $request->filled('amount_received')
                && round((float) $request->amount_received, 2) < round($total, 2)) {
                abort(422, sprintf(
                    'Pago insuficiente. El dinero recibido (%s) es menor que el total a pagar (%s).',
                    number_format((float) $request->amount_received, 2),
                    number_format($total, 2),
                ));
            }

            $prefix = SystemSettings::value('invoice_prefix', 'INV');
            $lastSale = Sale::orderBy('id', 'desc')->lockForUpdate()->first();
            $lastNumber = $lastSale ? (int) Str::afterLast($lastSale->invoice_number, '-') : 0;
            $invoiceNumber = $prefix.'-'.str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'user_id' => auth()->id(),
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'notes' => $request->notes,
            ]);

            $session->addSale($total, $request->payment_method);

            $notifyOutOfStock = (bool) SystemSettings::value('notify_out_of_stock', true);
            $outOfStockProducts = [];

            foreach ($saleItems as $item) {
                $sale->items()->create($item);

                $product = Product::find($item['product_id']);
                $previousStock = $product->stock;
                $newStock = $previousStock - $item['quantity'];

                $product->stock = $newStock;
                $product->save();

                InventoryMovement::create([
                    'product_id' => $item['product_id'],
                    'user_id' => auth()->id(),
                    'type' => 'exit',
                    'quantity' => $item['quantity'],
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reference' => "Venta #{$sale->invoice_number}",
                ]);

                if ($notifyOutOfStock && $previousStock > 0 && $newStock <= 0) {
                    $outOfStockProducts[] = $product->name;
                }
            }

            return [
                'sale' => new SaleResource($sale->load(['user', 'cashRegister', 'items.product'])),
                'out_of_stock_products' => $outOfStockProducts,
                'message' => 'Venta registrada exitosamente',
            ];
        });
    }

    public function show(Request $request, Sale $sale): array
    {
        abort_if($request->user()->isLimitedToOwnRecords() && $sale->user_id !== $request->user()->id, 404);

        return [
            'sale' => new SaleResource($sale->load(['user', 'cashRegister', 'items.product'])),
        ];
    }

    public function update(UpdateSaleRequest $request, Sale $sale): array
    {
        DB::transaction(function () use ($request, $sale) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($request->input('status') === 'cancelled' && $sale->status === 'completed') {
                $this->reverseSale($sale);
            }

            $sale->update($request->validated());
        });

        $sale->refresh();

        return [
            'sale' => new SaleResource($sale->load(['user', 'items.product'])),
            'message' => 'Venta actualizada exitosamente',
        ];
    }

    /**
     * Put the sold units back in stock and, while the register is still open,
     * take the sale out of its totals so the cash count stays correct.
     */
    private function reverseSale(Sale $sale): void
    {
        if ($sale->returns()->where('status', '!=', 'rejected')->exists()) {
            abort(422, 'No se puede cancelar una venta con devoluciones registradas.');
        }

        foreach ($sale->items as $item) {
            $product = Product::lockForUpdate()->find($item->product_id);
            $previousStock = $product->stock;
            $product->increment('stock', $item->quantity);

            InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => 'entry',
                'quantity' => $item->quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $previousStock + $item->quantity,
                'reference' => "Cancelación venta #{$sale->invoice_number}",
            ]);
        }

        $session = CashRegisterSession::lockForUpdate()->find($sale->cash_register_session_id);
        if ($session?->status === 'open') {
            $session->addSale(-$sale->total, $sale->payment_method);
        }
    }

    public function destroy(Sale $sale): array
    {
        if ($sale->status === 'completed') {
            abort(422, 'No se puede eliminar una venta completada');
        }

        $sale->delete();

        return [
            'message' => 'Venta eliminada exitosamente',
        ];
    }
}
