<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegisterSession>
 */
class CashRegisterSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'user_id' => User::factory(),
            'opening_balance' => 0,
            'expected_closing' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ];
    }

    public function openFor(User $user, float $openingBalance = 100.00): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'opening_balance' => $openingBalance,
            'expected_closing' => $openingBalance,
            'status' => 'open',
            'opened_at' => now(),
        ])->afterCreating(function (CashRegisterSession $session): void {
            $session->cashRegister()->update(['status' => 'open']);
        });
    }
}
