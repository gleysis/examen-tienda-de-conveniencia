<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $suppliers = $query->latest()->paginate($request->integer('per_page', 15));

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request): array
    {
        $supplier = Supplier::create($request->validated());

        return [
            'supplier' => new SupplierResource($supplier),
            'message' => 'Proveedor creado exitosamente',
        ];
    }

    public function show(Supplier $supplier): array
    {
        $supplier->load('products');

        return [
            'supplier' => new SupplierResource($supplier),
        ];
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): array
    {
        $supplier->update($request->validated());

        return [
            'supplier' => new SupplierResource($supplier),
            'message' => 'Proveedor actualizado exitosamente',
        ];
    }

    public function destroy(Supplier $supplier): array
    {
        $supplier->delete();

        return [
            'message' => 'Proveedor eliminado exitosamente',
        ];
    }
}
