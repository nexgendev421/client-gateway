<?php

namespace Database\Factories;

use App\Enums\LicenceStatus;
use App\Models\Customer;
use App\Models\Licence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Licence> */
class LicenceFactory extends Factory
{
    protected $model = Licence::class;

    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'plan' => 'standard', 'status' => LicenceStatus::Trial, 'starts_at' => now(), 'valid_until' => now()->addMonth(), 'grace_until' => null, 'max_stores' => null, 'max_devices' => null, 'metadata' => null];
    }
}
