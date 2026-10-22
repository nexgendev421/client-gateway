<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<DeviceCredential> */
class DeviceCredentialFactory extends Factory
{
    protected $model = DeviceCredential::class;

    public function definition(): array
    {
        return ['device_id' => Device::factory(), 'token_hash' => Hash::make(fake()->unique()->sha256()), 'token_prefix' => fake()->lexify('????????'), 'expires_at' => now()->addYear(), 'last_used_at' => null, 'revoked_at' => null];
    }
}
