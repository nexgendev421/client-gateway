<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_routes_require_admin_authentication(): void
    {
        $device = Device::factory()->create();

        $this->get(route('admin.devices.index'))->assertRedirect(route('login'));
        $this->get(route('admin.devices.show', $device))->assertRedirect(route('login'));
    }

    public function test_authorized_admin_can_search_filter_and_view_device_context(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $store = Store::factory()->create(['name' => 'Huddersfield Main']);
        $device = Device::factory()->for($store)->create([
            'name' => 'Front Till',
            'public_id' => 'SYP-FRONT-001',
            'platform' => 'windows',
            'last_seen_at' => now()->subHour(),
        ]);
        Device::factory()->create(['name' => 'Other Android', 'platform' => 'android', 'status' => DeviceStatus::Disabled]);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'public_id' => 'SYP-FRONT-001', 'store_id' => $store->id]);

        $this->actingAs($admin)->get(route('admin.devices.index', ['q' => 'SYP-FRONT']))
            ->assertOk()->assertSee('Front Till')->assertSee('SYP-FRONT-001')->assertDontSee('Other Android');
        $this->get(route('admin.devices.index', [
            'customer_id' => $store->customer_id, 'store_id' => $store->id,
            'platform' => 'windows', 'status' => 'active', 'seen' => 'recent',
        ]))->assertOk()->assertSee('Front Till')->assertDontSee('Other Android');

        $this->get(route('admin.devices.show', $device))->assertOk()
            ->assertSee('Device information')->assertSee('Huddersfield Main')
            ->assertSee($store->customer->business_name)->assertSee('Inherited licence')
            ->assertSee('Rename device')->assertSee('Move to another store');
    }

    public function test_support_can_manage_devices_while_read_only_can_only_view(): void
    {
        $device = Device::factory()->create();

        $this->actingAs(User::factory()->withRole(AdminRole::Support)->create());
        $this->get(route('admin.devices.index'))->assertOk();
        $this->get(route('admin.devices.show', $device))->assertOk();
        $this->post(route('admin.devices.disable', $device))->assertRedirect();
        $this->post(route('admin.devices.revoke', $device))->assertForbidden();

        $this->actingAs(User::factory()->withRole(AdminRole::ReadOnly)->create());
        $this->get(route('admin.devices.index'))->assertOk();
        $this->get(route('admin.devices.show', $device))->assertOk();
        $this->post(route('admin.devices.reactivate', $device))->assertForbidden();
    }

    public function test_admin_can_rename_and_move_device_only_within_its_customer(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $customer = Customer::factory()->create();
        $source = Store::factory()->for($customer)->create();
        $destination = Store::factory()->for($customer)->create();
        $otherCustomerStore = Store::factory()->create();
        $device = Device::factory()->for($source)->create(['name' => 'Register One']);

        $this->actingAs($admin)->patch(route('admin.devices.rename', $device), ['name' => 'Front Counter'])
            ->assertRedirect(route('admin.devices.show', $device));
        $this->assertSame('Front Counter', $device->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.renamed', 'resource_id' => (string) $device->id]);

        $this->patch(route('admin.devices.move', $device), ['store_id' => $destination->id])->assertRedirect();
        $this->assertSame($destination->id, $device->fresh()->store_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.moved', 'resource_id' => (string) $device->id]);

        $this->from(route('admin.devices.show', $device))->patch(route('admin.devices.move', $device), ['store_id' => $otherCustomerStore->id])
            ->assertSessionHasErrors('store_id');
        $this->assertSame($destination->id, $device->fresh()->store_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'device.moved')->count());
    }

    public function test_admin_can_disable_reactivate_and_permanently_revoke_device(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $device = Device::factory()->create();
        $this->actingAs($admin);

        $this->post(route('admin.devices.disable', $device))->assertRedirect();
        $this->assertSame(DeviceStatus::Disabled, $device->fresh()->status);
        $this->post(route('admin.devices.reactivate', $device))->assertRedirect();
        $this->assertSame(DeviceStatus::Active, $device->fresh()->status);
        $this->post(route('admin.devices.revoke', $device))->assertRedirect();
        $this->assertSame(DeviceStatus::Revoked, $device->fresh()->status);
        $this->post(route('admin.devices.reactivate', $device))->assertStatus(409);
        $this->post(route('admin.devices.disable', $device))->assertStatus(409);

        $this->assertDatabaseHas('audit_logs', ['action' => 'device.disabled', 'resource_id' => (string) $device->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.reactivated', 'resource_id' => (string) $device->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.revoked', 'resource_id' => (string) $device->id]);
    }

    public function test_revoked_device_cannot_be_renamed_or_moved(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $device = Device::factory()->create(['status' => DeviceStatus::Revoked]);
        $this->actingAs($admin);

        $this->patch(route('admin.devices.rename', $device), ['name' => 'Changed'])->assertStatus(409);
        $this->patch(route('admin.devices.move', $device), ['store_id' => Store::factory()->for($device->store->customer)->create()->id])->assertStatus(409);
    }
}
