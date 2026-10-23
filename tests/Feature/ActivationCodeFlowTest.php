<?php

namespace Tests\Feature;

use App\Enums\ActivationCodeStatus;
use App\Enums\AdminRole;
use App\Enums\LicenceStatus;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Licence;
use App\Models\Store;
use App\Models\User;
use App\Services\ProvisioningTokenException;
use App\Services\ProvisioningTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class ActivationCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_activation_registers_device_consumes_code_and_returns_signed_provisioning_token(): void
    {
        $store = Store::factory()->create(['store_code' => 'asiaHud']);
        $this->addLicence($store);
        $code = $this->makeCode($store, '12345678', ['intended_device_name' => 'Front Counter']);

        $this->fakeTokenIssuer();
        $response = $this->postJson(route('api.v1.activate'), [
            'store_code' => 'ASIAHUD',
            'activation_code' => '1234-5678',
            'device_id' => 'SYP-K7P2-93XF',
            'device_name' => 'Front Counter',
            'platform' => 'windows',
            'app_version' => '1.0.0',
        ])->assertCreated()->assertJson([
            'business_name' => $store->customer->trading_name ?: $store->customer->business_name,
            'store_name' => $store->name,
            'endpoint_url' => $store->endpoint_url,
            'device_reference' => 'SYP-K7P2-93XF',
        ])->assertJsonStructure(['business_name', 'store_name', 'endpoint_url', 'device_reference', 'provisioning_token', 'expires_at']);

        $response->assertHeader('Cache-Control', 'no-store, private');
        $device = Device::query()->where('public_id', 'SYP-K7P2-93XF')->firstOrFail();
        $this->assertSame('Front Counter', $device->name);
        $this->assertSame('windows', $device->platform->value);
        $this->assertSame(1, $code->fresh()->used_count);
        $this->assertSame(ActivationCodeStatus::Used, $code->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activation_code.used', 'resource_id' => (string) $code->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.provisioned', 'resource_id' => (string) $device->id]);
        $auditMetadata = DB::table('audit_logs')->where('action', 'activation_code.used')->value('metadata');
        $this->assertStringNotContainsString('12345678', $auditMetadata);

        $this->assertSame('signed.test-token', $response->json('provisioning_token'));
    }

    public function test_incorrect_expired_revoked_and_consumed_codes_are_rejected_without_disclosing_which(): void
    {
        $store = Store::factory()->create();
        $this->addLicence($store);
        $expired = $this->makeCode($store, '11112222', ['expires_at' => now()->subMinute()]);
        $revoked = $this->makeCode($store, '33334444', ['status' => ActivationCodeStatus::Revoked]);
        $used = $this->makeCode($store, '55556666', ['status' => ActivationCodeStatus::Used, 'used_count' => 1]);

        foreach (['99990000', '11112222', '33334444', '55556666'] as $candidate) {
            $this->postJson(route('api.v1.activate'), $this->payload($store, $candidate, 'SYP-TEST-0001'))
                ->assertUnprocessable()->assertJsonPath('code', 'activation_failed');
        }

        $this->assertSame(ActivationCodeStatus::Expired, $expired->fresh()->status);
        $this->assertSame(0, $revoked->fresh()->used_count);
        $this->assertSame(1, $used->fresh()->used_count);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'activation_code.used', 'resource_id' => (string) $expired->id]);
    }

    public function test_maximum_uses_are_enforced_for_multi_use_codes(): void
    {
        $store = Store::factory()->create();
        $this->addLicence($store);
        $code = $this->makeCode($store, '71727374', ['max_uses' => 2]);
        $payload = $this->payload($store, '7172-7374', 'SYP-TEST-0001');
        $this->fakeTokenIssuer(2);

        $this->postJson(route('api.v1.activate'), $payload)->assertCreated();
        $this->assertSame(ActivationCodeStatus::Active, $code->fresh()->status);
        $this->assertSame(1, $code->fresh()->used_count);
        $payload['device_id'] = 'SYP-TEST-0002';
        $this->postJson(route('api.v1.activate'), $payload)->assertCreated();
        $this->assertSame(ActivationCodeStatus::Used, $code->fresh()->status);
        $this->assertSame(2, $code->fresh()->used_count);
        $payload['device_id'] = 'SYP-TEST-0003';
        $this->postJson(route('api.v1.activate'), $payload)->assertUnprocessable();
        $this->assertSame(2, $code->fresh()->used_count);
        $this->assertSame(2, AuditLog::query()->where('action', 'activation_code.used')->count());
    }

    public function test_intended_device_name_must_match_before_code_is_consumed(): void
    {
        $store = Store::factory()->create();
        $this->addLicence($store);
        $code = $this->makeCode($store, '61626364', ['intended_device_name' => 'Till A']);

        $this->postJson(route('api.v1.activate'), $this->payload($store, '6162-6364', 'SYP-TEST-0001', 'Till B'))->assertUnprocessable();

        $this->assertSame(ActivationCodeStatus::Active, $code->fresh()->status);
        $this->assertSame(0, $code->fresh()->used_count);
    }

    public function test_admin_can_revoke_active_code_and_global_list_shows_only_masked_value(): void
    {
        $store = Store::factory()->create();
        $code = $this->makeCode($store, '90919293');
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->get(route('admin.activation-codes.index'))
            ->assertOk()->assertSee('****-9293')->assertDontSee('9091-9293');
        $this->post(route('admin.activation-codes.revoke', $code))
            ->assertRedirect()->assertSessionHas('status', 'Activation code revoked.');

        $this->assertSame(ActivationCodeStatus::Revoked, $code->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activation_code.revoked', 'resource_id' => (string) $code->id]);
    }

    public function test_store_activation_tab_and_generation_form_show_options_without_historical_plaintext(): void
    {
        $store = Store::factory()->create();
        $code = $this->makeCode($store, '38394041');
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->get(route('admin.stores.activation-codes.index', $store))
            ->assertOk()->assertSee('Activation Codes')->assertSee('****-4041')->assertDontSee('3839-4041');
        $this->get(route('admin.stores.activation-codes.create', $store))
            ->assertOk()->assertSee('1 hour')->assertSee('24 hours')->assertSee('7 days')->assertSee('Maximum uses');
        $this->from(route('admin.stores.activation-codes.create', $store))
            ->post(route('admin.stores.activation-codes.store', $store), ['expires_in_hours' => 2, 'max_uses' => 0])
            ->assertSessionHasErrors(['expires_in_hours', 'max_uses']);

        $this->assertSame(0, $code->fresh()->used_count);
        $this->assertDatabaseCount('activation_codes', 1);
    }

    public function test_activation_validation_is_rate_limited(): void
    {
        $store = Store::factory()->create();
        RateLimiter::clear('device-activation|127.0.0.1');
        $payload = $this->payload($store, '0000-0000', 'SYP-TEST-0001');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('api.v1.activate'), $payload)->assertUnprocessable();
        }

        $this->postJson(route('api.v1.activate'), $payload)->assertTooManyRequests();
    }

    public function test_customer_licence_and_store_device_limits_are_checked_without_consuming_code(): void
    {
        $store = Store::factory()->create(['max_devices' => 1]);
        $this->addLicence($store, ['max_devices' => 10]);
        Device::factory()->for($store)->create();
        $code = $this->makeCode($store, '20212223');

        $this->postJson(route('api.v1.activate'), $this->payload($store, '2021-2223', 'SYP-TEST-0001'))->assertUnprocessable();
        $this->assertSame(0, $code->fresh()->used_count);
        $this->assertSame(1, Device::query()->where('store_id', $store->id)->count());
    }

    public function test_customer_device_limit_is_applied_across_all_its_stores(): void
    {
        $store = Store::factory()->create(['max_devices' => 5]);
        $this->addLicence($store, ['max_devices' => 1]);
        Device::factory()->for($store)->create();
        $secondStore = Store::factory()->for($store->customer)->create(['max_devices' => 5]);
        $code = $this->makeCode($secondStore, '44445555');

        $this->postJson(route('api.v1.activate'), $this->payload($secondStore, '4444-5555', 'SYP-TEST-0002'))->assertUnprocessable();
        $this->assertSame(0, $code->fresh()->used_count);
        $this->assertSame(0, Device::query()->where('store_id', $secondStore->id)->count());
    }

    public function test_revoked_devices_free_capacity_and_grace_licence_allows_registration(): void
    {
        $store = Store::factory()->create(['max_devices' => 1]);
        $this->addLicence($store, [
            'max_devices' => 1,
            'status' => LicenceStatus::Grace,
            'valid_until' => now()->subDay(),
            'grace_until' => now()->addWeek(),
        ]);
        Device::factory()->for($store)->create(['status' => 'revoked']);
        $code = $this->makeCode($store, '31323334');
        $this->fakeTokenIssuer();

        $this->postJson(route('api.v1.activate'), $this->payload($store, '3132-3334', 'SYP-TEST-GRACE'))->assertCreated();
        $this->assertSame(1, $code->fresh()->used_count);
        $this->assertSame(2, Device::query()->where('store_id', $store->id)->count());
    }

    public function test_expired_licence_blocks_activation_without_consuming_code(): void
    {
        $store = Store::factory()->create();
        $this->addLicence($store, ['valid_until' => now()->subSecond()]);
        $code = $this->makeCode($store, '41424344');

        $this->postJson(route('api.v1.activate'), $this->payload($store, '4142-4344', 'SYP-TEST-EXPIRED'))->assertUnprocessable();
        $this->assertSame(0, $code->fresh()->used_count);
        $this->assertDatabaseMissing('devices', ['public_id' => 'SYP-TEST-EXPIRED']);
    }

    public function test_token_signing_failure_rolls_back_device_and_code_consumption(): void
    {
        $store = Store::factory()->create();
        $this->addLicence($store);
        $code = $this->makeCode($store, '62636465');
        $issuer = Mockery::mock(ProvisioningTokenIssuer::class);
        $issuer->shouldReceive('issue')->once()->andThrow(new ProvisioningTokenException);
        $this->app->instance(ProvisioningTokenIssuer::class, $issuer);

        $this->postJson(route('api.v1.activate'), $this->payload($store, '6263-6465', 'SYP-TEST-0001'))
            ->assertServiceUnavailable()->assertDontSee('private key');

        $this->assertSame(0, $code->fresh()->used_count);
        $this->assertSame(ActivationCodeStatus::Active, $code->fresh()->status);
        $this->assertDatabaseMissing('devices', ['public_id' => 'SYP-TEST-0001']);
    }

    private function makeCode(Store $store, string $digits, array $overrides = []): ActivationCode
    {
        return ActivationCode::factory()->for($store)->create(array_merge([
            'code_hash' => Hash::make($digits),
            'masked_suffix' => substr($digits, -4),
            'expires_at' => now()->addDay(),
            'max_uses' => 1,
            'used_count' => 0,
            'status' => ActivationCodeStatus::Active,
        ], $overrides));
    }

    private function addLicence(Store $store, array $overrides = []): void
    {
        Licence::factory()->for($store->customer)->create(array_merge([
            'status' => LicenceStatus::Active,
            'starts_at' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ], $overrides));
    }

    private function payload(Store $store, string $code, string $deviceId, string $deviceName = 'Test Till'): array
    {
        return [
            'store_code' => $store->store_code,
            'activation_code' => $code,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'platform' => 'windows',
            'app_version' => '1.0.0',
        ];
    }

    private function fakeTokenIssuer(int $times = 1): void
    {
        $issuer = Mockery::mock(ProvisioningTokenIssuer::class);
        $issuer->shouldReceive('issue')->times($times)->andReturn([
            'token' => 'signed.test-token',
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ]);
        $this->app->instance(ProvisioningTokenIssuer::class, $issuer);
    }
}
