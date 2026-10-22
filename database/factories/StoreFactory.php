<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'name' => fake()->company().' Store', 'store_code' => Str::upper(Str::random(10)), 'endpoint_url' => fake()->unique()->url(), 'status' => StoreStatus::Active, 'max_devices' => 10];
    }
}
