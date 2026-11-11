<?php

namespace Database\Factories;

use App\Models\CashCount;
use App\Models\CashRegisterSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCount>
 */
class CashCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_session_id' => CashRegisterSession::factory(),
            'expected_amount' => 100,
            'counted_amount' => 100,
            'difference' => 0,
            'type' => CashCount::TYPE_CLOSING,
        ];
    }

    public function withDifference(float $difference): static
    {
        return $this->state(fn (array $attributes): array => [
            'counted_amount' => $attributes['expected_amount'] + $difference,
            'difference' => $difference,
        ]);
    }
}
