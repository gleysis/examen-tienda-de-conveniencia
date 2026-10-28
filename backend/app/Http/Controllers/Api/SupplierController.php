<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): array
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

        $suppliers = $query->latest()->get();

        return [
            'suppliers' => $suppliers,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'payment_terms' => 'integer|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'current_balance' => 'numeric|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create($request->all());

        return [
            'supplier' => $supplier,
            'message' => 'Proveedor creado exitosamente',
        ];
    }

    public function show(Supplier $supplier): array
    {
        $supplier->load('products');

        return [
            'supplier' => $supplier,
        ];
    }

    public function update(Request $request, Supplier $supplier): array
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'payment_terms' => 'sometimes|integer|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'current_balance' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($request->all());

        return [
            'supplier' => $supplier,
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
