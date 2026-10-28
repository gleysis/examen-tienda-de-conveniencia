<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->numerify('SKU-#####'),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 500),
            'cost' => fake()->randomFloat(2, 1, 250),
            'stock' => fake()->numberBetween(0, 100),
            'min_stock' => 5,
            'category_id' => Category::factory(),
            'is_active' => true,
        ];
    }
}
