<?php

namespace Database\Factories;

use App\Models\SystemSettingChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSettingChange>
 */
class SystemSettingChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'store_name',
            'old_value' => fake()->company(),
            'new_value' => fake()->company(),
            'user_id' => User::factory(),
        ];
    }
}
