<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\DevicePlatform;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\HealthCheck;
use App\Models\Licence;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_persist_domain_entities_and_relationships(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $store = Store::factory()->for($customer)->create();
        $device = Device::factory()->for($store)->create(['platform' => DevicePlatform::Android]);
        $code = ActivationCode::factory()->for($store)->for($user, 'creator')->create();
        $credential = DeviceCredential::factory()->for($device)->create();
        $licence = Licence::factory()->for($customer)->create();
        $health = HealthCheck::factory()->for($store)->create();
        $audit = AuditLog::factory()->for($user, 'actor')->create();
        $setting = SystemSetting::factory()->create();

        $this->assertSame($customer->id, $store->customer->id);
        $this->assertSame($store->id, $device->store->id);
        $this->assertSame($user->id, $code->creator->id);
        $this->assertSame($device->id, $credential->device->id);
        $this->assertSame($customer->id, $licence->customer->id);
        $this->assertSame($store->id, $health->store->id);
        $this->assertSame($user->id, $audit->actor->id);
        $this->assertIsArray($setting->value);
        $this->assertSame(CustomerStatus::Active, $customer->status);
        $this->assertArrayNotHasKey('code_hash', $code->toArray());
        $this->assertArrayNotHasKey('token_hash', $credential->toArray());
    }

    public function test_unique_store_code_and_device_public_id_are_enforced(): void
    {
        $customer = Customer::factory()->create();
        $store = Store::factory()->for($customer)->create(['store_code' => 'STORE01']);
        $this->expectException(QueryException::class);
        Store::factory()->for($customer)->create(['store_code' => 'STORE01']);
    }

    public function test_customer_delete_is_restricted_while_store_history_exists(): void
    {
        $customer = Customer::factory()->create();
        Store::factory()->for($customer)->create();

        $this->expectException(QueryException::class);
        $customer->delete();
    }

    public function test_audit_metadata_strips_sensitive_keys_recursively(): void
    {
        $audit = AuditLog::factory()->create(['metadata' => [
            'changed_fields' => ['name'],
            'activation_code' => 'raw-code',
            'context' => ['device_token' => 'raw-token', 'store_id' => 7],
            'password_hash' => 'secret',
        ]]);

        $this->assertSame(['changed_fields' => ['name'], 'context' => ['store_id' => 7]], $audit->fresh()->getRawOriginal('metadata') ? json_decode($audit->fresh()->getRawOriginal('metadata'), true) : []);
    }

    public function test_device_public_id_and_activation_hash_have_database_uniqueness(): void
    {
        $store = Store::factory()->create();
        Device::factory()->for($store)->create(['public_id' => 'b89037b4-529e-4c1d-a156-fc49c3df6adc']);
        ActivationCode::factory()->for($store)->create(['code_hash' => 'same-hash']);

        try {
            Device::factory()->for($store)->create(['public_id' => 'b89037b4-529e-4c1d-a156-fc49c3df6adc']);
            $this->fail('Expected duplicate public id to fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('devices', 1);
        }

        $this->expectException(QueryException::class);
        ActivationCode::factory()->for($store)->create(['code_hash' => 'same-hash']);
    }
}
