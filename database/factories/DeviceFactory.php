<?php

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return ['store_id' => Store::factory(), 'public_id' => (string) Str::uuid(), 'name' => fake()->word().' device', 'platform' => fake()->randomElement(DevicePlatform::cases()), 'os_version' => fake()->numerify('#.#.#'), 'app_version' => '1.0.0', 'status' => DeviceStatus::Active, 'registered_at' => now(), 'last_seen_at' => null, 'last_ip' => null];
    }
}
