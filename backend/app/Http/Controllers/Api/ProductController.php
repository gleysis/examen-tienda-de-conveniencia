<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with(['category:id,name,slug', 'supplier:id,name'])
            ->where('is_active', true);

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

        $products = $query->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): array
    {
        $product = Product::create($request->validated());

        return [
            'product' => new ProductResource($product->load('category')),
            'message' => 'Producto creado exitosamente',
        ];
    }

    public function show(Product $product): array
    {
        return [
            'product' => new ProductResource($product->load(['category', 'supplier'])),
        ];
    }

    public function update(UpdateProductRequest $request, Product $product): array
    {
        $product->update($request->validated());

        return [
            'product' => new ProductResource($product->load(['category', 'supplier'])),
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
