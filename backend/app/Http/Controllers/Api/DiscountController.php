<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;
use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiscountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
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

        $discounts = $query->latest()->paginate($request->integer('per_page', 15));

        return DiscountResource::collection($discounts);
    }

    public function store(StoreDiscountRequest $request): array
    {
        $discount = Discount::create($request->validated());

        return [
            'discount' => new DiscountResource($discount),
            'message' => 'Descuento creado exitosamente',
        ];
    }

    public function show(Discount $discount): array
    {
        return [
            'discount' => new DiscountResource($discount),
        ];
    }

    public function update(UpdateDiscountRequest $request, Discount $discount): array
    {
        $discount->update($request->validated());

        return [
            'discount' => new DiscountResource($discount),
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

        if (! SystemSettings::value('allow_discounts', true)) {
            return [
                'valid' => false,
                'message' => 'Los descuentos en ventas están deshabilitados',
            ];
        }

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
            'discount' => new DiscountResource($discount),
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
