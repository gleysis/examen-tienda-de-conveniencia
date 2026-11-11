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
            'status' => 'closed',
        ];
    }

    /**
     * The register is created already open, with a live session for the
     * given cashier.
     */
    public function openFor(User $user, float $openingBalance = 100.00): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'status' => 'open',
        ])->afterCreating(fn (CashRegister $register) => $register->sessions()->create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'opening_balance' => $openingBalance,
            'expected_closing' => $openingBalance,
            'status' => 'open',
            'opened_at' => now(),
        ]));
    }
}
