<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return ['actor_id' => null, 'action' => 'customer.updated', 'resource_type' => 'Customer', 'resource_id' => (string) fake()->numberBetween(1, 1000), 'metadata' => ['changed_fields' => ['business_name']], 'ip_address' => fake()->ipv4(), 'occurred_at' => now()];
    }
}
