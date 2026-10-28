<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    public function index(Request $request): array
    {
        $query = Discount::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $discounts = $query->latest()->get();

        return [
            'discounts' => $discounts,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:discounts',
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed_amount,buy_x_get_y,free_shipping',
            'value' => 'required|numeric|min:0',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
            'applicable_to' => 'required|in:all_products,specific_products,categories,customers',
            'description' => 'nullable|string',
        ]);

        $discount = Discount::create($request->all());

        return [
            'discount' => $discount,
            'message' => 'Descuento creado exitosamente',
        ];
    }

    public function show(Discount $discount): array
    {
        return [
            'discount' => $discount,
        ];
    }

    public function update(Request $request, Discount $discount): array
    {
        $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('discounts')->ignore($discount->id)],
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:percentage,fixed_amount,buy_x_get_y,free_shipping',
            'value' => 'sometimes|numeric|min:0',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'applicable_to' => 'sometimes|in:all_products,specific_products,categories,customers',
            'description' => 'nullable|string',
        ]);

        $discount->update($request->all());

        return [
            'discount' => $discount,
            'message' => 'Descuento actualizado exitosamente',
        ];
    }

    public function destroy(Discount $discount): array
    {
        $discount->delete();

        return [
            'message' => 'Descuento eliminado exitosamente',
        ];
    }

    public function validate(Request $request): array
    {
        $request->validate([
            'code' => 'required|string',
            'purchase_amount' => 'required|numeric|min:0',
        ]);

        $discount = Discount::where('code', $request->code)->first();

        if (! $discount) {
            return [
                'valid' => false,
                'message' => 'Código de descuento no encontrado',
            ];
        }

        if (! $discount->isValid()) {
            return [
                'valid' => false,
                'message' => 'Este código de descuento no es válido o ha expirado',
            ];
        }

        if ($discount->minimum_purchase && $request->purchase_amount < $discount->minimum_purchase) {
            return [
                'valid' => false,
                'message' => "El monto mínimo de compra es {$discount->minimum_purchase}",
            ];
        }

        $discountAmount = $this->calculateDiscountAmount($discount, $request->purchase_amount);

        return [
            'valid' => true,
            'discount' => $discount,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $request->purchase_amount - $discountAmount),
        ];
    }

    private function calculateDiscountAmount(Discount $discount, float $purchaseAmount): float
    {
        $discountAmount = match ($discount->type) {
            'percentage' => $purchaseAmount * ($discount->value / 100),
            'fixed_amount' => $discount->value,
            'free_shipping' => 0,
            default => 0,
        };

        if ($discount->maximum_discount) {
            $discountAmount = min($discountAmount, $discount->maximum_discount);
        }

        return min($discountAmount, $purchaseAmount);
    }
}
