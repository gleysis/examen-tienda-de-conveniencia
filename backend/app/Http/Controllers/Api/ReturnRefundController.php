<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReturnRefundRequest;
use App\Http\Requests\UpdateReturnRefundRequest;
use App\Http\Resources\ReturnRefundResource;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ReturnRefund;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReturnRefundController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ReturnRefund::with(['sale', 'saleItem', 'product', 'user']);

        if ($request->user()->isLimitedToOwnRecords()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('search')) {
            $query->where('return_number', 'like', '%'.$request->search.'%');
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

        $returns = $query->latest()->paginate($request->integer('per_page', 15));

        return ReturnRefundResource::collection($returns);
    }

    public function store(StoreReturnRefundRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $saleItem = SaleItem::with('sale')->lockForUpdate()->findOrFail($request->sale_item_id);

            if ($saleItem->sale_id !== (int) $request->sale_id || $saleItem->product_id !== (int) $request->product_id) {
                abort(422, 'El producto seleccionado no pertenece a esa venta');
            }

            if ($saleItem->sale->status !== 'completed') {
                abort(422, 'Solo se pueden devolver productos de ventas completadas');
            }

            $returnableQuantity = $saleItem->returnableQuantity();
            if ($request->quantity > $returnableQuantity) {
                abort(422, "La cantidad a devolver excede lo disponible para devolución ({$returnableQuantity})");
            }

            $maxRefund = $saleItem->paidAmountFor($request->quantity);
            if (round((float) $request->refund_amount, 2) > $maxRefund) {
                abort(422, 'El monto a reembolsar no puede superar lo pagado por el cliente ('.number_format($maxRefund, 2).')');
            }

            $returnNumber = 'RET-'.str_pad(ReturnRefund::count() + 1, 6, '0', STR_PAD_LEFT);

            $returnRefund = ReturnRefund::create([
                'return_number' => $returnNumber,
                'sale_id' => $request->sale_id,
                'sale_item_id' => $request->sale_item_id,
                'product_id' => $request->product_id,
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
                'return_refund' => new ReturnRefundResource($returnRefund->load(['sale', 'saleItem', 'product', 'user'])),
                'message' => 'Solicitud de devolución creada exitosamente',
            ];
        });
    }

    public function show(Request $request, ReturnRefund $returnRefund): array
    {
        abort_if($request->user()->isLimitedToOwnRecords() && $returnRefund->user_id !== $request->user()->id, 404);

        return [
            'return_refund' => new ReturnRefundResource($returnRefund->load(['sale', 'saleItem', 'product', 'user'])),
        ];
    }

    public function update(UpdateReturnRefundRequest $request, ReturnRefund $returnRefund): array
    {
        if ($returnRefund->status === 'processed') {
            abort(422, 'No se puede modificar una devolución procesada');
        }

        $isReopening = $returnRefund->status === 'rejected' && $request->filled('status') && $request->status !== 'rejected';
        if ($isReopening && $returnRefund->quantity > $returnRefund->saleItem->returnableQuantity()) {
            abort(422, 'Esos productos ya están incluidos en otra devolución');
        }

        $returnRefund->update($request->validated());

        return [
            'return_refund' => new ReturnRefundResource($returnRefund->load(['sale', 'saleItem', 'product', 'user'])),
            'message' => 'Devolución actualizada exitosamente',
        ];
    }

    public function process(Request $request, ReturnRefund $returnRefund): array
    {
        return DB::transaction(function () use ($request, $returnRefund) {
            $returnRefund = ReturnRefund::lockForUpdate()->findOrFail($returnRefund->id);

            if ($returnRefund->status !== 'approved') {
                abort(422, 'Solo se pueden procesar devoluciones aprobadas');
            }

            if ($returnRefund->refund_method === 'cash') {
                $register = CashRegister::where('user_id', auth()->id())->open()->first();
                abort_unless($register, 422, 'Abre tu caja para entregar un reembolso en efectivo.');

                $movement = $register->movements()->create([
                    'user_id' => auth()->id(),
                    'type' => CashMovement::TYPE_WITHDRAWAL,
                    'amount' => $returnRefund->refund_amount,
                    'reason' => "Reembolso devolución #{$returnRefund->return_number}",
                ]);
                $register->addMovement($movement->type, (float) $movement->amount);
            }

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
                'return_refund' => new ReturnRefundResource($returnRefund->load(['sale', 'saleItem', 'product', 'user'])),
                'message' => 'Devolución procesada exitosamente',
            ];
        });
    }

    public function destroy(ReturnRefund $returnRefund): array
    {
        if ($returnRefund->status === 'processed') {
            abort(422, 'No se puede eliminar una devolución procesada');
        }

        $returnRefund->delete();

        return [
            'message' => 'Devolución eliminada exitosamente',
        ];
    }
}
