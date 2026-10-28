<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): array
    {
        $query = Product::with(['category:id,name,slug', 'supplier:id,name'])
            ->where('is_active', true)
            ->select('id', 'name', 'sku', 'price', 'cost', 'stock', 'min_stock', 'category_id', 'supplier_id', 'barcode', 'is_active');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->get();

        return [
            'products' => $products,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:products',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'barcode' => 'nullable|string|max:255',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($request->all());

        return [
            'product' => $product->load('category'),
            'message' => 'Producto creado exitosamente',
        ];
    }

    public function show(Product $product): array
    {
        return [
            'product' => $product->load(['category', 'supplier']),
        ];
    }

    public function update(Request $request, Product $product): array
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'min_stock' => 'sometimes|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'barcode' => 'nullable|string|max:255',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product->update($request->all());

        return [
            'product' => $product->load(['category', 'supplier']),
            'message' => 'Producto actualizado exitosamente',
        ];
    }

    public function destroy(Product $product): array
    {
        $product->delete();

        return [
            'message' => 'Producto eliminado exitosamente',
        ];
    }
}
