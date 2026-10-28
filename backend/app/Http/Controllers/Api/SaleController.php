<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Discount;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request): array
    {
        $query = Sale::with(['user', 'cashRegister', 'items.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sales = $query->latest()->get();

        return [
            'sales' => $sales,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
            'discount_code' => 'nullable|string|exists:discounts,code',
            'payment_method' => 'required|in:cash,card,transfer',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $subtotal = 0;
            $saleItems = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    return [
                        'error' => "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, Solicitado: {$item['quantity']}",
                    ];
                }

                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemSubtotal;

                $saleItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $discount = $request->discount ?? 0;

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

            $tax = $request->tax ?? 0;
            $total = $subtotal - $discount + $tax;

            $lastSale = Sale::orderBy('id', 'desc')->first();
            $lastNumber = $lastSale ? (int) substr($lastSale->invoice_number, 4) : 0;
            $invoiceNumber = 'INV-'.str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'user_id' => auth()->id(),
                'cash_register_id' => $request->cash_register_id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'notes' => $request->notes,
            ]);

            if ($request->cash_register_id) {
                $cashRegister = CashRegister::find($request->cash_register_id);
                if ($cashRegister) {
                    $cashRegister->addSale($total, $request->payment_method);
                }
            }

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
            }

            return [
                'sale' => $sale->load(['user', 'cashRegister', 'items.product']),
                'message' => 'Venta registrada exitosamente',
            ];
        });
    }

    public function show(Sale $sale): array
    {
        return [
            'sale' => $sale->load(['user', 'cashRegister', 'items.product']),
        ];
    }

    public function update(Request $request, Sale $sale): array
    {
        $request->validate([
            'status' => 'sometimes|in:pending,completed,cancelled',
            'payment_method' => 'sometimes|in:cash,card,transfer',
            'notes' => 'nullable|string',
        ]);

        $sale->update($request->all());

        return [
            'sale' => $sale->load(['user', 'items.product']),
            'message' => 'Venta actualizada exitosamente',
        ];
    }

    public function destroy(Sale $sale): array
    {
        if ($sale->status === 'completed') {
            return [
                'error' => 'No se puede eliminar una venta completada',
            ];
        }

        $sale->delete();

        return [
            'message' => 'Venta eliminada exitosamente',
        ];
    }
}
