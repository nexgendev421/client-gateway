<?php

namespace Database\Factories;

use App\Enums\HealthCheckStatus;
use App\Models\HealthCheck;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HealthCheck> */
class HealthCheckFactory extends Factory
{
    protected $model = HealthCheck::class;

    public function definition(): array
    {
        return ['store_id' => Store::factory(), 'status' => HealthCheckStatus::Online, 'response_time_ms' => fake()->numberBetween(10, 500), 'pos_version' => '1.0.0', 'api_version' => '1', 'checked_at' => now(), 'safe_error_code' => null, 'safe_error_message' => null];
    }
}
