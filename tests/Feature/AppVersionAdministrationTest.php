<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_windows_and_android_version_settings(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin)->get(route('admin.settings.app-versions'))
            ->assertOk()->assertSee('App version management')->assertSee('Minimum supported version');

        $this->put(route('admin.settings.app-versions.update'), $this->validSettings())
            ->assertRedirect(route('admin.settings.app-versions'))->assertSessionHas('status', 'App version settings saved.');

        $stored = SystemSetting::query()->where('key', 'app_versions')->firstOrFail();
        $this->assertSame('2.3.0', $stored->value['windows']['latest_version']);
        $this->assertTrue($stored->value['android']['mandatory_update']);
        $audit = AuditLog::query()->where('action', 'settings.app_versions_updated')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('downloads.example.com', $audit->metadata['windows']['download_host']);
        $this->assertArrayNotHasKey('release_notes', $audit->metadata['windows']);
    }

    public function test_non_admin_roles_cannot_manage_app_version_settings(): void
    {
        $this->actingAs(User::factory()->withRole(AdminRole::Support)->create())
            ->get(route('admin.settings.app-versions'))->assertForbidden();
        $this->put(route('admin.settings.app-versions.update'), $this->validSettings())->assertForbidden();
        $this->assertDatabaseMissing('system_settings', ['key' => 'app_versions']);
    }

    public function test_insecure_download_links_and_inverted_version_bounds_are_rejected(): void
    {
        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

        $this->from(route('admin.settings.app-versions'))
            ->put(route('admin.settings.app-versions.update'), $this->validSettings([
                'windows' => ['download_url' => 'http://downloads.example.com/setup.exe'],
            ]))->assertSessionHasErrors('versions.windows.download_url');

        $this->from(route('admin.settings.app-versions'))
            ->put(route('admin.settings.app-versions.update'), $this->validSettings([
                'android' => ['latest_version' => '1.2.0', 'minimum_supported_version' => '1.3.0'],
            ]))->assertSessionHasErrors('versions.android.minimum_supported_version');

        $this->assertDatabaseMissing('system_settings', ['key' => 'app_versions']);
    }

    private function validSettings(array $overrides = []): array
    {
        $defaults = [
            'windows' => [
                'latest_version' => '2.3.0',
                'minimum_supported_version' => '2.0.0',
                'download_url' => 'https://downloads.example.com/siyazpos/windows',
                'mandatory_update' => 0,
                'release_notes' => 'Windows stability improvements',
            ],
            'android' => [
                'latest_version' => '5.1.0',
                'minimum_supported_version' => '4.8.0',
                'download_url' => 'https://downloads.example.com/siyazpos/android',
                'mandatory_update' => 1,
                'release_notes' => 'Android updates',
            ],
        ];
        foreach ($overrides as $platform => $settings) {
            $defaults[$platform] = array_merge($defaults[$platform], $settings);
        }

        return ['versions' => $defaults];
    }
}
