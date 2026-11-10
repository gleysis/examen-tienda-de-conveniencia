<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Caja '.fake()->unique()->numberBetween(1, 9999),
            'opening_balance' => 0,
            'current_balance' => 0,
            'expected_closing' => 0,
            'status' => 'closed',
        ];
    }

    public function openFor(User $user, float $openingBalance = 100.00): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'status' => 'open',
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'expected_closing' => $openingBalance,
            'opened_at' => now(),
        ]);
    }
}
