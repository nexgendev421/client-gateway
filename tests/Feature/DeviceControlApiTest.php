<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Enums\LicenceStatus;
use App\Models\Device;
use App\Models\Licence;
use App\Models\SystemSetting;
use App\Services\DeviceCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceControlApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_updates_only_allowed_telemetry_and_last_seen_fields(): void
    {
        $device = Device::factory()->create([
            'platform' => 'windows',
            'app_version' => '1.0.0',
            'os_version' => 'Windows 10',
            'last_seen_at' => now()->subDay(),
        ]);
        $credential = app(DeviceCredentialService::class)->issue($device);

        $this->postJson(route('api.v1.device.heartbeat'), [
            'app_version' => '1.2.4',
            'os_version' => 'Windows 11 24H2',
            'platform' => 'windows',
        ], ['Authorization' => 'Bearer '.$credential['token']])
            ->assertOk()->assertJsonPath('status', 'ok')->assertJsonStructure(['last_seen_at'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $device->refresh();
        $this->assertSame('1.2.4', $device->app_version);
        $this->assertSame('Windows 11 24H2', $device->os_version);
        $this->assertSame('127.0.0.1', $device->last_ip);
        $this->assertTrue($device->last_seen_at->gt(now()->subMinute()));
    }

    public function test_heartbeat_rejects_platform_mismatch_and_unsafe_telemetry(): void
    {
        $device = Device::factory()->create(['platform' => 'android', 'app_version' => '1.0.0']);
        $credential = app(DeviceCredentialService::class)->issue($device);

        $this->postJson(route('api.v1.device.heartbeat'), [
            'app_version' => '2.0.0', 'os_version' => 'Android 14', 'platform' => 'windows',
        ], ['Authorization' => 'Bearer '.$credential['token']])->assertUnprocessable();
        $this->assertSame('1.0.0', $device->fresh()->app_version);

        $this->postJson(route('api.v1.device.heartbeat'), [
            'app_version' => '<script>alert(1)</script>', 'os_version' => 'Android 14', 'platform' => 'android',
        ], ['Authorization' => 'Bearer '.$credential['token']])->assertUnprocessable();
        $this->assertSame('1.0.0', $device->fresh()->app_version);
    }

    public function test_config_returns_minimal_customer_store_device_licence_and_version_data(): void
    {
        $device = Device::factory()->create(['platform' => 'android', 'name' => 'Front Tablet']);
        $license = Licence::factory()->for($device->store->customer)->create([
            'plan' => 'Retail Plus',
            'status' => LicenceStatus::Grace,
            'valid_until' => now()->subDay(),
            'grace_until' => now()->addWeek(),
        ]);
        SystemSetting::query()->create(['key' => 'minimum_android_version', 'value' => '1.4.0']);
        SystemSetting::query()->create(['key' => 'recommended_android_version', 'value' => '1.7.2']);
        SystemSetting::query()->create(['key' => 'maintenance_notice', 'value' => '<b>Scheduled maintenance tonight</b>']);
        $credential = app(DeviceCredentialService::class)->issue($device);

        $response = $this->getJson(route('api.v1.device.config'), ['Authorization' => 'Bearer '.$credential['token']])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('business_name', $device->store->customer->business_name)
            ->assertJsonPath('store_name', $device->store->name)
            ->assertJsonPath('endpoint_url', $device->store->endpoint_url)
            ->assertJsonPath('device.name', 'Front Tablet')
            ->assertJsonPath('device.status', 'active')
            ->assertJsonPath('licence.plan', 'Retail Plus')
            ->assertJsonPath('licence.status', 'grace')
            ->assertJsonPath('app_versions.platform', 'android')
            ->assertJsonPath('app_versions.minimum_supported', '1.4.0')
            ->assertJsonPath('app_versions.recommended', '1.7.2')
            ->assertJsonPath('app_versions.latest_version', '1.7.2')
            ->assertJsonPath('app_versions.mandatory_update', false)
            ->assertJsonPath('maintenance_notice', 'Scheduled maintenance tonight');

        $this->assertSame(['business_name', 'store_name', 'endpoint_url', 'device', 'licence', 'app_versions', 'heartbeat_expected_interval_minutes', 'support_contact', 'maintenance_notice'], array_keys($response->json()));
        $this->assertArrayNotHasKey('id', $response->json('device'));
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertNotNull($license->fresh());
    }

    public function test_config_returns_safe_platform_update_details_from_admin_settings(): void
    {
        $device = Device::factory()->create(['platform' => 'windows']);
        SystemSetting::query()->create(['key' => 'app_versions', 'value' => [
            'windows' => [
                'latest_version' => '2.0.0',
                'minimum_supported_version' => '1.7.0',
                'download_url' => 'https://downloads.example.com/router/windows',
                'mandatory_update' => true,
                'release_notes' => '<b>Security fixes</b>',
            ],
        ]]);
        $credential = app(DeviceCredentialService::class)->issue($device);

        $this->getJson(route('api.v1.device.config'), ['Authorization' => 'Bearer '.$credential['token']])
            ->assertOk()
            ->assertJsonPath('app_versions.latest_version', '2.0.0')
            ->assertJsonPath('app_versions.minimum_supported', '1.7.0')
            ->assertJsonPath('app_versions.download_url', 'https://downloads.example.com/router/windows')
            ->assertJsonPath('app_versions.mandatory_update', true)
            ->assertJsonPath('app_versions.release_notes', 'Security fixes');
    }

    public function test_device_control_routes_require_a_valid_active_device_credential(): void
    {
        $this->postJson(route('api.v1.device.heartbeat'), [
            'app_version' => '1.0.0', 'os_version' => null, 'platform' => 'windows',
        ])->assertUnauthorized();

        $device = Device::factory()->create();
        $credential = app(DeviceCredentialService::class)->issue($device);
        $device->update(['status' => DeviceStatus::Disabled]);

        $this->getJson(route('api.v1.device.config'), ['Authorization' => 'Bearer '.$credential['token']])
            ->assertUnauthorized()->assertJson(['message' => 'Device authentication failed.']);
    }
}
