<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index(): array
    {
        $categories = Category::where('is_active', true)->get();

        return [
            'categories' => $categories,
        ];
    }

    public function store(StoreCategoryRequest $request): array
    {
        $category = Category::create($request->validated());

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

    public function update(UpdateCategoryRequest $request, Category $category): array
    {
        $category->update($request->validated());

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
