<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ReturnRefund;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnRefundController extends Controller
{
    public function index(Request $request): array
    {
        $query = ReturnRefund::with(['sale', 'saleItem', 'product', 'customer', 'user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $returns = $query->latest()->get();

        return [
            'returns' => $returns,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'sale_item_id' => 'required|exists:sale_items,id',
            'product_id' => 'required|exists:products,id',
            'customer_id' => 'nullable|exists:customers,id',
            'quantity' => 'required|integer|min:1',
            'refund_amount' => 'required|numeric|min:0',
            'reason' => 'required|in:defective,wrong_item,no_longer_needed,customer_preference,other',
            'reason_description' => 'nullable|string',
            'refund_method' => 'required|in:cash,card,transfer,store_credit',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $saleItem = SaleItem::findOrFail($request->sale_item_id);

            if ($request->quantity > $saleItem->quantity) {
                return [
                    'error' => 'La cantidad a devolver no puede exceder la cantidad original comprada',
                ];
            }

            $returnNumber = 'RET-'.str_pad(ReturnRefund::count() + 1, 6, '0', STR_PAD_LEFT);

            $returnRefund = ReturnRefund::create([
                'return_number' => $returnNumber,
                'sale_id' => $request->sale_id,
                'sale_item_id' => $request->sale_item_id,
                'product_id' => $request->product_id,
                'customer_id' => $request->customer_id,
                'user_id' => auth()->id(),
                'quantity' => $request->quantity,
                'refund_amount' => $request->refund_amount,
                'reason' => $request->reason,
                'reason_description' => $request->reason_description,
                'status' => 'pending',
                'refund_method' => $request->refund_method,
                'notes' => $request->notes,
            ]);

            return [
                'return_refund' => $returnRefund->load(['sale', 'saleItem', 'product', 'customer', 'user']),
                'message' => 'Solicitud de devolución creada exitosamente',
            ];
        });
    }

    public function show(ReturnRefund $returnRefund): array
    {
        return [
            'return_refund' => $returnRefund->load(['sale', 'saleItem', 'product', 'customer', 'user']),
        ];
    }

    public function update(Request $request, ReturnRefund $returnRefund): array
    {
        $request->validate([
            'status' => 'sometimes|in:pending,approved,rejected,processed',
            'refund_method' => 'sometimes|in:cash,card,transfer,store_credit',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $returnRefund->update($request->all());

        return [
            'return_refund' => $returnRefund->load(['sale', 'saleItem', 'product', 'customer', 'user']),
            'message' => 'Devolución actualizada exitosamente',
        ];
    }

    public function process(Request $request, ReturnRefund $returnRefund): array
    {
        if ($returnRefund->status !== 'approved') {
            return [
                'error' => 'Solo se pueden procesar devoluciones aprobadas',
            ];
        }

        return DB::transaction(function () use ($request, $returnRefund) {
            $product = Product::findOrFail($returnRefund->product_id);
            $previousStock = $product->stock;
            $newStock = $previousStock + $returnRefund->quantity;

            $product->stock = $newStock;
            $product->save();

            InventoryMovement::create([
                'product_id' => $returnRefund->product_id,
                'user_id' => auth()->id(),
                'type' => 'entry',
                'quantity' => $returnRefund->quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference' => "Devolución #{$returnRefund->return_number}",
                'notes' => "Devolución por: {$returnRefund->reason}",
            ]);

            $returnRefund->update([
                'status' => 'processed',
                'processed_date' => now(),
                'reference_number' => $request->reference_number,
            ]);

            return [
                'return_refund' => $returnRefund->load(['sale', 'saleItem', 'product', 'customer', 'user']),
                'message' => 'Devolución procesada exitosamente',
            ];
        });
    }

    public function destroy(ReturnRefund $returnRefund): array
    {
        if ($returnRefund->status === 'processed') {
            return [
                'error' => 'No se puede eliminar una devolución procesada',
            ];
        }

        $returnRefund->delete();

        return [
            'message' => 'Devolución eliminada exitosamente',
        ];
    }
}
