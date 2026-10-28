<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 1000);
        $tax = round($subtotal * 0.1, 2);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => 0,
            'total' => $subtotal + $tax,
            'payment_method' => fake()->randomElement(['cash', 'card', 'transfer']),
            'status' => 'completed',
        ];
    }
}
