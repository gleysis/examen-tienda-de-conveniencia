<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request): array
    {
        $query = InventoryMovement::with(['product:id,name,sku', 'user:id,name'])
            ->select('id', 'product_id', 'user_id', 'type', 'quantity', 'previous_stock', 'new_stock', 'reference', 'created_at');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->latest()->get();

        return [
            'movements' => $movements,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:entry,exit',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $product = Product::findOrFail($request->product_id);
            $previousStock = $product->stock;
            $quantity = $request->quantity;

            if ($request->type === 'exit') {
                if ($previousStock < $quantity) {
                    return [
                        'error' => "Stock insuficiente. Disponible: {$previousStock}, Solicitado: {$quantity}",
                    ];
                }
                $newStock = $previousStock - $quantity;
            } else {
                $newStock = $previousStock + $quantity;
            }

            $product->stock = $newStock;
            $product->save();

            $movement = InventoryMovement::create([
                'product_id' => $request->product_id,
                'user_id' => auth()->id(),
                'type' => $request->type,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference' => $request->reference,
                'notes' => $request->notes,
            ]);

            return [
                'movement' => $movement->load(['product', 'user']),
                'message' => 'Movimiento de inventario registrado exitosamente',
            ];
        });
    }

    public function show(InventoryMovement $movement): array
    {
        return [
            'movement' => $movement->load(['product', 'user']),
        ];
    }

    public function lowStock(): array
    {
        $products = Product::with('category')
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('is_active', true)
            ->get();

        return [
            'products' => $products,
        ];
    }

    public function report(Request $request): array
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get();

        $totalStock = $products->sum('stock');
        $totalValue = $products->sum(function ($product) {
            return $product->stock * $product->cost;
        });

        $lowStockCount = $products->where('stock', '<=', 'min_stock')->count();
        $lowStockProducts = $products->where('stock', '<=', 'min_stock')->values();

        return [
            'products' => $products,
            'summary' => [
                'total_products' => $products->count(),
                'total_stock' => $totalStock,
                'total_value' => $totalValue,
                'low_stock_count' => $lowStockCount,
                'low_stock_products' => $lowStockProducts,
            ],
        ];
    }
}
