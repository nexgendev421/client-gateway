<?php

namespace Database\Factories;

use App\Enums\ActivationCodeStatus;
use App\Models\ActivationCode;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<ActivationCode> */
class ActivationCodeFactory extends Factory
{
    protected $model = ActivationCode::class;

    public function definition(): array
    {
        return ['store_id' => Store::factory(), 'code_hash' => Hash::make(fake()->unique()->bothify('????-########')), 'masked_suffix' => fake()->numerify('####'), 'expires_at' => now()->addHour(), 'max_uses' => 1, 'used_count' => 0, 'status' => ActivationCodeStatus::Active, 'intended_device_name' => null, 'created_by' => null];
    }
}
