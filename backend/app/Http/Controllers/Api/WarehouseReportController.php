<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WarehouseReportController extends Controller
{
    public function movements(Request $request): array
    {
        $query = InventoryMovement::with(['product:id,name,sku,cost', 'user:id,name'])
            ->latest('created_at');

        $this->applyMovementFilters($query, $request);

        $movements = $query->limit(500)->get();

        return [
            'movements' => $movements,
            'summary' => [
                'total' => $movements->count(),
                'entries' => $movements->where('type', 'entry')->sum('quantity'),
                'exits' => $movements->where('type', 'exit')->sum('quantity'),
            ],
        ];
    }

    public function lowStock(): array
    {
        $products = Product::with(['category:id,name', 'supplier:id,name'])
            ->where('is_active', true)
            ->whereNotNull('min_stock')
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $missing = max($product->min_stock - $product->stock, 0);
                $product->missing_qty = $missing;
                $product->suggested_qty = $product->max_stock !== null
                    ? max($product->max_stock - $product->stock, 0)
                    : max($missing, $product->min_stock);

                return $product;
            });

        return [
            'products' => $products,
            'summary' => [
                'count' => $products->count(),
                'missing_total' => $products->sum('missing_qty'),
                'estimated_cost' => $products->sum(fn ($p) => $p->suggested_qty * $p->cost),
            ],
        ];
    }

    public function inbound(Request $request): array
    {
        $query = InventoryMovement::with(['product:id,name,sku,cost', 'user:id,name'])
            ->where('type', 'entry')
            ->latest('created_at');

        $this->applyMovementFilters($query, $request);

        $movements = $query->limit(500)->get()->map(function (InventoryMovement $movement) {
            $movement->unit_cost = $movement->product?->cost ?? 0;
            $movement->total_cost = $movement->unit_cost * $movement->quantity;

            return $movement;
        });

        return [
            'movements' => $movements,
            'summary' => [
                'total' => $movements->count(),
                'units' => $movements->sum('quantity'),
                'total_cost' => $movements->sum('total_cost'),
            ],
        ];
    }

    public function losses(Request $request): array
    {
        $query = InventoryMovement::with(['product:id,name,sku,cost', 'user:id,name'])
            ->where('type', 'exit')
            ->where(function (Builder $q) {
                $q->whereNull('reference')->orWhere('reference', 'not like', '%INV%');
            })
            ->latest('created_at');

        $this->applyMovementFilters($query, $request);

        $movements = $query->limit(500)->get()->map(function (InventoryMovement $movement) {
            $movement->loss_value = ($movement->product?->cost ?? 0) * $movement->quantity;

            return $movement;
        });

        return [
            'movements' => $movements,
            'summary' => [
                'total' => $movements->count(),
                'units' => $movements->sum('quantity'),
                'loss_value' => $movements->sum('loss_value'),
            ],
        ];
    }

    public function valuation(): array
    {
        $items = Product::with('category:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category?->name ?? 'Sin categoría',
                'stock' => $product->stock,
                'cost' => $product->cost,
                'total_value' => $product->stock * $product->cost,
            ]);

        $byCategory = $items->groupBy('category')
            ->map(fn (Collection $items, string $category) => [
                'category' => $category,
                'total_stock' => $items->sum('stock'),
                'total_value' => $items->sum('total_value'),
            ])
            ->sortByDesc('total_value')
            ->values();

        return [
            'items' => $items,
            'by_category' => $byCategory,
            'total_value' => $items->sum('total_value'),
            'total_stock' => $items->sum('stock'),
        ];
    }

    public function slowMoving(): array
    {
        $lastMovements = InventoryMovement::selectRaw('product_id, MAX(created_at) as last_at')
            ->groupBy('product_id')
            ->pluck('last_at', 'product_id');

        $items = Product::with('category:id,name')
            ->where('is_active', true)
            ->get()
            ->map(function (Product $product) use ($lastMovements) {
                $lastAt = $lastMovements[$product->id] ?? null;
                $lastDate = $lastAt ? Carbon::parse($lastAt) : $product->created_at;

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Sin categoría',
                    'stock' => $product->stock,
                    'cost' => $product->cost,
                    'idle_value' => $product->stock * $product->cost,
                    'last_movement_at' => $lastAt,
                    'days_idle' => $lastDate ? (int) $lastDate->diffInDays(now()) : null,
                ];
            })
            ->sortByDesc('days_idle')
            ->values();

        return [
            'items' => $items,
            'summary' => [
                'over_30_days' => $items->where('days_idle', '>=', 30)->count(),
                'idle_value' => $items->where('days_idle', '>=', 30)->sum('idle_value'),
            ],
        ];
    }

    public function supplierPurchases(Request $request): array
    {
        $query = InventoryMovement::with(['product.supplier:id,name'])
            ->where('type', 'entry')
            ->latest('created_at');

        $this->applyMovementFilters($query, $request);

        $bySupplier = $query->limit(2000)->get()
            ->groupBy(fn (InventoryMovement $m) => $m->product?->supplier?->name ?? 'Sin proveedor')
            ->map(fn (Collection $movements, string $name) => [
                'supplier' => $name,
                'entries' => $movements->count(),
                'products' => $movements->pluck('product_id')->unique()->count(),
                'units' => $movements->sum('quantity'),
                'total_cost' => $movements->sum(
                    fn (InventoryMovement $m) => $m->quantity * ($m->product?->cost ?? 0)
                ),
                'last_entry' => $movements->max('created_at'),
            ])
            ->sortByDesc('total_cost')
            ->values();

        return [
            'suppliers' => $bySupplier,
            'summary' => [
                'total_cost' => $bySupplier->sum('total_cost'),
                'units' => $bySupplier->sum('units'),
            ],
        ];
    }

    private function applyMovementFilters(Builder $query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('product')) {
            $search = $request->product;
            $query->whereHas('product', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user')) {
            $search = $request->user;
            $query->whereHas('user', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
    }
}
