<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electrónicos',
                'slug' => 'electronicos',
                'description' => 'Productos electrónicos y dispositivos',
            ],
            [
                'name' => 'Alimentos',
                'slug' => 'alimentos',
                'description' => 'Productos alimenticios y bebidas',
            ],
            [
                'name' => 'Ropa',
                'slug' => 'ropa',
                'description' => 'Vestimenta y accesorios',
            ],
            [
                'name' => 'Hogar',
                'slug' => 'hogar',
                'description' => 'Artículos para el hogar',
            ],
            [
                'name' => 'Salud',
                'slug' => 'salud',
                'description' => 'Productos de salud y bienestar',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
