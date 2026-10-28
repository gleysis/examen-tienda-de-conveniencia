<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all()->keyBy('id');

        $products = [
            [
                'name' => 'Laptop HP',
                'sku' => 'LAP-001',
                'description' => 'Laptop HP 15.6" Intel Core i5',
                'price' => 899.99,
                'cost' => 650.00,
                'stock' => 15,
                'min_stock' => 5,
                'category_id' => $categories->firstWhere('slug', 'electronicos')?->id,
                'barcode' => '1234567890123',
            ],
            [
                'name' => 'Mouse Inalámbrico',
                'sku' => 'MOU-001',
                'description' => 'Mouse inalámbrico USB',
                'price' => 29.99,
                'cost' => 15.00,
                'stock' => 50,
                'min_stock' => 10,
                'category_id' => $categories->firstWhere('slug', 'electronicos')?->id,
                'barcode' => '1234567890124',
            ],
            [
                'name' => 'Arroz Integral',
                'sku' => 'ALI-001',
                'description' => 'Arroz integral 1kg',
                'price' => 3.99,
                'cost' => 2.50,
                'stock' => 100,
                'min_stock' => 20,
                'category_id' => $categories->firstWhere('slug', 'alimentos')?->id,
                'barcode' => '1234567890125',
            ],
            [
                'name' => 'Camiseta Básica',
                'sku' => 'ROP-001',
                'description' => 'Camiseta de algodón 100%',
                'price' => 19.99,
                'cost' => 8.00,
                'stock' => 3,
                'min_stock' => 10,
                'category_id' => $categories->firstWhere('slug', 'ropa')?->id,
                'barcode' => '1234567890126',
            ],
            [
                'name' => 'Lámpara de Mesa',
                'sku' => 'HOG-001',
                'description' => 'Lámpara de mesa LED',
                'price' => 45.99,
                'cost' => 25.00,
                'stock' => 8,
                'min_stock' => 5,
                'category_id' => $categories->firstWhere('slug', 'hogar')?->id,
                'barcode' => '1234567890127',
            ],
            [
                'name' => 'Vitaminas C',
                'sku' => 'SAL-001',
                'description' => 'Suplemento de vitamina C 60 tabletas',
                'price' => 12.99,
                'cost' => 6.00,
                'stock' => 4,
                'min_stock' => 15,
                'category_id' => $categories->firstWhere('slug', 'salud')?->id,
                'barcode' => '1234567890128',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
