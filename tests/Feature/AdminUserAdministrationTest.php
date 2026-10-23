<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_admin_accounts_with_hashed_passwords_and_audit(): void
    {
        $actor = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($actor)->post(route('admin.admin-users.store'), $this->validData())
            ->assertRedirect();

        $user = User::query()->where('email', 'new.admin@example.com')->firstOrFail();
        $this->assertSame(AdminRole::Support, $user->role);
        $this->assertSame(AdminStatus::Active, $user->status);
        $this->assertTrue(Hash::check('securePass2026!', $user->password));
        $this->assertNotSame('securePass2026!', $user->password);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'admin.created',
            'resource_id' => (string) $user->id,
        ]);
        $this->assertStringNotContainsString('securePass2026!', json_encode(AuditLog::query()->latest('id')->value('metadata')));
    }

    public function test_super_admin_can_change_role_status_and_reset_password_with_separate_audit_events(): void
    {
        $actor = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $target = User::factory()->withRole(AdminRole::ReadOnly)->create();

        $this->actingAs($actor)->put(route('admin.admin-users.update', $target), $this->validData([
            'name' => $target->name,
            'email' => $target->email,
            'role' => AdminRole::Support->value,
            'status' => AdminStatus::Disabled->value,
            'password' => 'updatedPass2026!',
            'password_confirmation' => 'updatedPass2026!',
        ]))->assertRedirect(route('admin.admin-users.edit', $target));

        $target->refresh();
        $this->assertSame(AdminRole::Support, $target->role);
        $this->assertSame(AdminStatus::Disabled, $target->status);
        $this->assertTrue(Hash::check('updatedPass2026!', $target->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.role_changed', 'resource_id' => (string) $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.disabled', 'resource_id' => (string) $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password_reset', 'resource_id' => (string) $target->id]);
    }

    public function test_only_super_admins_can_manage_admin_users_and_read_only_users_cannot_manage_operations(): void
    {
        $support = User::factory()->withRole(AdminRole::Support)->create();
        $this->actingAs($support)->get(route('admin.admin-users.index'))->assertForbidden();
        $this->get(route('admin.section', ['section' => 'audit-logs']))->assertForbidden();
        $this->get(route('admin.settings.app-versions'))->assertForbidden();

        $readOnly = User::factory()->withRole(AdminRole::ReadOnly)->create();
        $this->actingAs($readOnly)->get(route('admin.customers.create'))->assertForbidden();
        $this->get(route('admin.stores.create'))->assertForbidden();
        $this->get(route('admin.devices.index'))->assertOk();
    }

    public function test_last_active_super_admin_cannot_be_demoted_or_disabled(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin);

        $this->from(route('admin.admin-users.edit', $admin))
            ->put(route('admin.admin-users.update', $admin), $this->validData([
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => AdminRole::Support->value,
                'status' => AdminStatus::Active->value,
                'password' => '',
                'password_confirmation' => '',
            ]))->assertSessionHasErrors('role');

        $this->assertSame(AdminRole::SuperAdmin, $admin->fresh()->role);
        $this->assertSame(AdminStatus::Active, $admin->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'admin.role_changed']);
    }

    public function test_admin_can_enable_a_disabled_account_but_cannot_disable_themselves(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $disabled = User::factory()->withRole(AdminRole::Support)->disabled()->create();
        $this->actingAs($admin);

        $this->post(route('admin.admin-users.enable', $disabled))->assertRedirect(route('admin.admin-users.edit', $disabled));
        $this->assertSame(AdminStatus::Active, $disabled->fresh()->status);

        $this->from(route('admin.admin-users.edit', $admin))
            ->post(route('admin.admin-users.disable', $admin))
            ->assertSessionHasErrors('status');
        $this->assertSame(AdminStatus::Active, $admin->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.enabled', 'resource_id' => (string) $disabled->id]);
    }

    public function test_password_validation_and_admin_search_are_enforced(): void
    {
        $actor = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($actor)->from(route('admin.admin-users.create'))
            ->post(route('admin.admin-users.store'), $this->validData(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        $target = User::factory()->withRole(AdminRole::Support)->create(['name' => 'Searchable Support', 'email' => 'find.me@example.com']);
        $this->get(route('admin.admin-users.index', ['q' => 'find.me']))
            ->assertOk()->assertSee($target->email)->assertDontSee($actor->email);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Support',
            'email' => 'new.admin@example.com',
            'role' => AdminRole::Support->value,
            'status' => AdminStatus::Active->value,
            'password' => 'securePass2026!',
            'password_confirmation' => 'securePass2026!',
        ], $overrides);
    }
}
