<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): array
    {
        $categories = Category::where('is_active', true)->get();

        return [
            'categories' => $categories,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category = Category::create($request->all());

        return [
            'category' => $category,
            'message' => 'Categoría creada exitosamente',
        ];
    }

    public function show(Category $category): array
    {
        return [
            'category' => $category,
        ];
    }

    public function update(Request $request, Category $category): array
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($request->all());

        return [
            'category' => $category,
            'message' => 'Categoría actualizada exitosamente',
        ];
    }

    public function destroy(Category $category): array
    {
        $category->delete();

        return [
            'message' => 'Categoría eliminada exitosamente',
        ];
    }
}
