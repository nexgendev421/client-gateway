<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DeviceConfigurationService;
use App\Services\RouterSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_save_central_settings(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()->assertSee('Default code expiry')->assertSee('Manage app versions');

        $this->put(route('admin.settings.update'), $this->validSettings([
            'default_activation_expiry_hours' => '168',
            'default_activation_max_uses' => '3',
            'health_check_frequency_minutes' => '15',
            'health_history_retention_days' => '180',
            'heartbeat_expected_interval_minutes' => '10',
            'default_licence_grace_days' => '14',
            'support_email' => 'help@siyazpos.example',
            'support_phone' => '+44 (0) 1234 567890',
            'maintenance_notice' => '<b>Planned maintenance tonight</b>',
            'signing_private_key' => 'must not be stored here',
        ]))->assertRedirect(route('admin.settings.index'));

        $settings = app(RouterSettings::class)->all();
        $this->assertSame(168, $settings['default_activation_expiry_hours']);
        $this->assertSame(3, $settings['default_activation_max_uses']);
        $this->assertSame(15, $settings['health_check_frequency_minutes']);
        $this->assertSame(180, $settings['health_history_retention_days']);
        $this->assertSame(10, $settings['heartbeat_expected_interval_minutes']);
        $this->assertSame(14, $settings['default_licence_grace_days']);
        $this->assertSame('Planned maintenance tonight', $settings['maintenance_notice']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.router_updated', 'actor_id' => $admin->id]);
        $this->assertStringNotContainsString('signing_private_key', json_encode(SystemSetting::query()->where('key', 'router_settings')->value('value')));
        $event = AuditLog::query()->where('action', 'settings.router_updated')->firstOrFail();
        $this->assertContains('maintenance_notice', $event->metadata['changed_settings']);
        $this->assertArrayNotHasKey('maintenance_notice', $event->metadata);
    }

    public function test_support_users_cannot_manage_central_settings(): void
    {
        $this->actingAs(User::factory()->withRole(AdminRole::Support)->create())
            ->get(route('admin.settings.index'))->assertForbidden();
        $this->put(route('admin.settings.update'), $this->validSettings())->assertForbidden();
    }

    public function test_central_settings_validate_allowed_intervals_and_contact_values(): void
    {
        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());
        $this->from(route('admin.settings.index'))->put(route('admin.settings.update'), $this->validSettings([
            'health_check_frequency_minutes' => '7',
            'default_activation_expiry_hours' => '3',
            'support_phone' => 'call me <script>',
        ]))->assertSessionHasErrors([
            'health_check_frequency_minutes',
            'default_activation_expiry_hours',
            'support_phone',
        ]);
        $this->assertDatabaseMissing('system_settings', ['key' => 'router_settings']);
    }

    public function test_safe_support_and_notice_settings_are_included_in_device_config(): void
    {
        $device = Device::factory()->create();
        SystemSetting::query()->create([
            'key' => 'router_settings',
            'value' => $this->validSettings([
                'support_email' => 'support@siyazpos.example',
                'support_phone' => '+441234567890',
                'maintenance_notice' => 'Scheduled work',
                'heartbeat_expected_interval_minutes' => 8,
            ]),
        ]);

        $config = app(DeviceConfigurationService::class)->forDevice($device);
        $this->assertSame(['email' => 'support@siyazpos.example', 'phone' => '+441234567890'], $config['support_contact']);
        $this->assertSame('Scheduled work', $config['maintenance_notice']);
        $this->assertSame(8, $config['heartbeat_expected_interval_minutes']);
    }

    public function test_activation_code_form_uses_central_defaults(): void
    {
        $store = Store::factory()->for(Customer::factory()->create())->create();
        SystemSetting::query()->create([
            'key' => 'router_settings',
            'value' => $this->validSettings([
                'default_activation_expiry_hours' => '168',
                'default_activation_max_uses' => '3',
            ]),
        ]);

        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->get(route('admin.stores.activation-codes.create', $store))->assertOk()
            ->assertSee('value="168" selected', false)
            ->assertSee('value="3"', false);
    }

    public function test_device_offline_filter_uses_three_expected_heartbeat_intervals(): void
    {
        $recent = Device::factory()->create(['name' => 'Recent device', 'last_seen_at' => now()->subMinutes(10)]);
        $offline = Device::factory()->create(['name' => 'Offline device', 'last_seen_at' => now()->subMinutes(20)]);

        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->get(route('admin.devices.index', ['seen' => 'offline']))->assertOk()
            ->assertSee('Offline device')
            ->assertDontSee('Recent device');
    }

    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'default_activation_expiry_hours' => '24',
            'default_activation_max_uses' => '1',
            'health_check_frequency_minutes' => '5',
            'health_history_retention_days' => '30',
            'heartbeat_expected_interval_minutes' => '5',
            'default_licence_grace_days' => '7',
            'support_email' => null,
            'support_phone' => null,
            'maintenance_notice' => null,
        ], $overrides);
    }
}
