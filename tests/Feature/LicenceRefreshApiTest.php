<?php

namespace Tests\Feature;

use App\Enums\LicenceStatus;
use App\Models\Device;
use App\Models\Licence;
use App\Services\DeviceCredentialService;
use App\Services\LicenceTokenIssuer;
use App\Services\ProvisioningTokenException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LicenceRefreshApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_requires_valid_device_authentication(): void
    {
        $this->getJson(route('api.v1.device.licence.refresh'))
            ->assertUnauthorized()->assertJson(['message' => 'Device authentication failed.']);
    }

    public function test_authenticated_device_receives_only_its_customer_signed_licence_and_state(): void
    {
        $device = Device::factory()->create();
        $licence = Licence::factory()->for($device->store->customer)->create([
            'plan' => 'Retail Plus',
            'status' => LicenceStatus::Grace,
            'valid_until' => now()->subDay(),
            'grace_until' => now()->addWeek(),
            'internal_notes' => 'Never return billing information',
        ]);
        $token = app(DeviceCredentialService::class)->issue($device)['token'];
        $issuer = Mockery::mock(LicenceTokenIssuer::class);
        $issuer->shouldReceive('issue')->once()->with(Mockery::on(fn (Licence $candidate): bool => $candidate->is($licence)))
            ->andReturn(['token' => 'signed.licence.document', 'issued_at' => now()->toIso8601String()]);
        $this->app->instance(LicenceTokenIssuer::class, $issuer);

        $response = $this->getJson(route('api.v1.device.licence.refresh'), ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJsonPath('state', 'grace')
            ->assertJsonPath('signed_licence', 'signed.licence.document')
            ->assertJsonPath('grace_until', $licence->grace_until->toIso8601String())
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(['state', 'signed_licence', 'issued_at', 'valid_until', 'grace_until'], array_keys($response->json()));
        $this->assertStringNotContainsString('billing', $response->getContent());
        $this->assertStringNotContainsString('internal_notes', $response->getContent());
    }

    public function test_unlicensed_customer_gets_clear_unlicensed_state_without_token(): void
    {
        $device = Device::factory()->create();
        $token = app(DeviceCredentialService::class)->issue($device)['token'];
        $issuer = Mockery::mock(LicenceTokenIssuer::class);
        $issuer->shouldNotReceive('issue');
        $this->app->instance(LicenceTokenIssuer::class, $issuer);

        $this->getJson(route('api.v1.device.licence.refresh'), ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson([
                'state' => 'unlicensed',
                'signed_licence' => null,
                'valid_until' => null,
                'grace_until' => null,
            ])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_signing_failure_returns_a_safe_service_error(): void
    {
        $device = Device::factory()->create();
        Licence::factory()->for($device->store->customer)->create();
        $token = app(DeviceCredentialService::class)->issue($device)['token'];
        $issuer = Mockery::mock(LicenceTokenIssuer::class);
        $issuer->shouldReceive('issue')->once()->andThrow(new ProvisioningTokenException);
        $this->app->instance(LicenceTokenIssuer::class, $issuer);

        $this->getJson(route('api.v1.device.licence.refresh'), ['Authorization' => 'Bearer '.$token])
            ->assertServiceUnavailable()->assertDontSee('private key')->assertJson(['message' => 'Licence refresh is temporarily unavailable.']);
    }
}
