<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_search_and_filter_audit_events(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'action' => 'customer.created',
            'resource_type' => Customer::class,
            'resource_id' => '42',
            'metadata' => ['changed_fields' => ['business_name']],
            'ip_address' => '192.0.2.10',
            'occurred_at' => now()->subDay(),
        ]);
        AuditLog::query()->create([
            'actor_id' => null,
            'action' => 'device.provisioned',
            'resource_type' => 'App\\Models\\Device',
            'resource_id' => 'device-9',
            'metadata' => ['platform' => 'windows'],
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'q' => 'customer.created',
            'resource' => 'customer',
            'actor_id' => (string) $admin->id,
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('customer.created')
            ->assertDontSee('device-9');
    }

    public function test_support_and_read_only_admins_cannot_view_audit_logs(): void
    {
        foreach ([AdminRole::Support, AdminRole::ReadOnly] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())
                ->get(route('admin.audit-logs.index'))
                ->assertForbidden();
        }
    }

    public function test_login_success_failure_and_logout_are_audited_without_email_or_credentials(): void
    {
        $admin = User::factory()->withRole(AdminRole::Support)->create();
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login_failed', 'actor_id' => null]);
        $failed = AuditLog::query()->where('action', 'admin.login_failed')->firstOrFail();
        $this->assertNull($failed->resource_id);
        $this->assertSame(['reason' => 'credentials_rejected'], $failed->metadata);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login_succeeded', 'actor_id' => $admin->id]);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.logout', 'actor_id' => $admin->id]);
    }

    public function test_audit_metadata_redacts_secrets_even_inside_values_and_nested_arrays(): void
    {
        $event = AuditLog::query()->create([
            'action' => 'test.redaction',
            'metadata' => [
                'api_token' => 'must not persist',
                'message' => 'Bearer abc.def token=super-secret activation code 1234-5678',
                'nested' => ['note' => 'credential=sypd_'.str_repeat('a', 30)],
            ],
            'occurred_at' => now(),
        ]);

        $stored = $event->fresh()->metadata;
        $this->assertArrayNotHasKey('api_token', $stored);
        $this->assertStringNotContainsString('1234-5678', json_encode($stored));
        $this->assertStringNotContainsString('super-secret', json_encode($stored));
        $this->assertStringNotContainsString('sypd_', json_encode($stored));
    }
}
