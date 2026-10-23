<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Http\Middleware\AuthenticateDeviceCredential;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceCredentialException;
use App\Services\DeviceCredentialService;
use App\Services\ProvisioningTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class DeviceCredentialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_credential_is_high_entropy_hashed_and_authenticates_as_bearer(): void
    {
        $device = Device::factory()->create();
        $issued = app(DeviceCredentialService::class)->issue($device);

        $this->assertSame(48, strlen($issued['token']));
        $this->assertStringStartsWith('sypd_', $issued['token']);
        $this->assertNotSame($issued['token'], $issued['credential']->token_hash);
        $this->assertSame(substr($issued['token'], 0, 12), $issued['credential']->token_prefix);
        $this->assertNull($issued['credential']->expires_at);
        $this->assertDatabaseMissing('device_credentials', ['token_hash' => $issued['token']]);
        $this->assertArrayNotHasKey('token_hash', $issued['credential']->toArray());

        Route::get('/_test/device-auth', fn (Request $request) => response()->json([
            'device_reference' => $request->attributes->get('device')->public_id,
        ]))->middleware(AuthenticateDeviceCredential::class);

        $this->getJson('/_test/device-auth', ['Authorization' => 'Bearer '.$issued['token']])
            ->assertOk()->assertJsonPath('device_reference', $device->public_id);
        $this->assertNotNull($issued['credential']->fresh()->last_used_at);
    }

    public function test_invalid_disabled_revoked_and_expired_credentials_cannot_authenticate(): void
    {
        $service = app(DeviceCredentialService::class);
        $active = Device::factory()->create();
        $issued = $service->issue($active);
        $this->assertNull($service->authenticate('sypd_'.str_repeat('x', 43)));

        $active->update(['status' => DeviceStatus::Disabled]);
        $this->assertNull($service->authenticate($issued['token']));
        $active->update(['status' => DeviceStatus::Active]);
        $issued['credential']->update(['revoked_at' => now()]);
        $this->assertNull($service->authenticate($issued['token']));

        $expiredDevice = Device::factory()->create();
        $expired = $service->issue($expiredDevice);
        $expired['credential']->update(['expires_at' => now()->subSecond()]);
        $this->assertNull($service->authenticate($expired['token']));

        $revokedDevice = Device::factory()->create(['status' => DeviceStatus::Revoked]);
        $this->expectException(DeviceCredentialException::class);
        $service->issue($revokedDevice);
    }

    public function test_rotation_revokes_previous_secret_and_returns_new_secret_only_once(): void
    {
        $device = Device::factory()->create();
        $service = app(DeviceCredentialService::class);
        $first = $service->issue($device);

        $response = $this->postJson(route('api.v1.device.credential.rotate'), [], [
            'Authorization' => 'Bearer '.$first['token'],
        ])->assertOk()->assertJsonStructure(['device_reference', 'credential', 'expires_at'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $secondToken = $response->json('credential');
        $this->assertNotSame($first['token'], $secondToken);
        $this->assertNotNull($first['credential']->fresh()->revoked_at);
        $this->assertNotNull($service->authenticate($secondToken));
        $this->assertNull($service->authenticate($first['token']));
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.credential_rotated', 'resource_id' => (string) $device->id]);
    }

    public function test_provisioning_exchange_issues_only_once_and_does_not_store_raw_jti_or_secret(): void
    {
        $device = Device::factory()->create();
        $claims = [
            'store_code' => $device->store->store_code,
            'device_id' => $device->public_id,
            'jti' => 'one-time-test-grant',
            'exp' => now()->addMinutes(5)->timestamp,
        ];
        $verifier = Mockery::mock(ProvisioningTokenVerifier::class);
        $verifier->shouldReceive('verify')->twice()->with('test-provisioning-token')->andReturn($claims);
        $this->app->instance(ProvisioningTokenVerifier::class, $verifier);

        $response = $this->postJson(route('api.v1.device.credentials.exchange'), [], [
            'Authorization' => 'Bearer test-provisioning-token',
        ])->assertCreated()->assertJsonStructure(['device_reference', 'credential', 'expires_at'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $secret = $response->json('credential');
        $this->assertDatabaseHas('provisioning_token_uses', [
            'device_id' => $device->id,
            'jti_hash' => hash('sha256', $claims['jti']),
        ]);
        $this->assertDatabaseMissing('provisioning_token_uses', ['jti_hash' => $claims['jti']]);
        $this->assertDatabaseMissing('device_credentials', ['token_hash' => $secret]);

        $this->postJson(route('api.v1.device.credentials.exchange'), [], [
            'Authorization' => 'Bearer test-provisioning-token',
        ])->assertUnauthorized()->assertJson(['message' => 'Device authentication failed.']);
    }

    public function test_device_can_revoke_its_current_credential(): void
    {
        $device = Device::factory()->create();
        $issued = app(DeviceCredentialService::class)->issue($device);

        $this->postJson(route('api.v1.device.credential.revoke'), [], [
            'Authorization' => 'Bearer '.$issued['token'],
        ])->assertNoContent();

        $this->assertNotNull($issued['credential']->fresh()->revoked_at);
        $this->assertNull(app(DeviceCredentialService::class)->authenticate($issued['token']));
    }

    public function test_admin_device_revocation_also_revokes_all_credentials(): void
    {
        $device = Device::factory()->create();
        $issued = app(DeviceCredentialService::class)->issue($device);
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->post(route('admin.devices.revoke', $device))->assertRedirect();

        $this->assertNotNull($issued['credential']->fresh()->revoked_at);
        $this->assertNull(app(DeviceCredentialService::class)->authenticate($issued['token']));
    }
}
