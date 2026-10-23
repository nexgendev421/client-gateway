<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\CustomerStatus;
use App\Enums\DevicePlatform;
use App\Enums\LicenceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Licence;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_list_searches_and_shows_database_counts_and_statuses(): void
    {
        $match = Customer::factory()->create(['business_name' => 'Pakasia Foods', 'trading_name' => 'Pakasia']);
        $store = Store::factory()->for($match)->create();
        Device::factory()->for($store)->create(['platform' => DevicePlatform::Windows]);
        Licence::factory()->for($match)->create(['status' => LicenceStatus::Active]);
        Customer::factory()->create(['business_name' => 'Other Business']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.customers.index', ['q' => 'Pakasia']))
            ->assertOk()
            ->assertSee('Pakasia Foods')
            ->assertSee('Pakasia')
            ->assertDontSee('Other Business')
            ->assertSee('Active')
            ->assertViewHas('customers', function ($customers): bool {
                $customer = $customers->first();

                return $customer->stores_count === 1
                    && $customer->devices_count === 1
                    && $customer->licence->status === LicenceStatus::Active;
            });
    }

    public function test_super_admin_can_create_customer_and_audit_entry_without_field_values(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'business_name' => 'Pakasia Foods Ltd',
            'trading_name' => 'Pakasia',
            'contact_name' => 'Sam Shah',
            'email' => 'sam@example.test',
            'phone' => '0123456789',
            'status' => 'active',
            'notes' => 'Call before onboarding.',
        ]);

        $customer = Customer::query()->where('business_name', 'Pakasia Foods Ltd')->firstOrFail();
        $response->assertRedirect(route('admin.customers.show', $customer));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'customer.created',
            'resource_type' => Customer::class,
            'resource_id' => (string) $customer->id,
        ]);
        $audit = AuditLog::query()->where('action', 'customer.created')->firstOrFail();
        $this->assertSame(['changed_fields' => ['business_name', 'trading_name', 'contact_name', 'email', 'phone', 'status', 'notes']], $audit->metadata);
        $this->assertStringNotContainsString('sam@example.test', json_encode($audit->metadata));
        $this->assertSame(CustomerStatus::Active, $customer->status);
    }

    public function test_invalid_customer_input_is_rejected_server_side(): void
    {
        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'business_name' => '',
                'email' => 'not-an-email',
                'status' => 'archived',
            ])
            ->assertRedirect(route('admin.customers.create'))
            ->assertSessionHasErrors(['business_name', 'email', 'status']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_edit_and_status_change_write_distinct_audit_events(): void
    {
        $customer = Customer::factory()->create(['business_name' => 'Old Name']);
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $response = $this->actingAs($admin)->put(route('admin.customers.update', $customer), [
            'business_name' => 'New Name',
            'trading_name' => null,
            'contact_name' => null,
            'email' => null,
            'phone' => null,
            'status' => 'suspended',
            'notes' => null,
        ]);

        $response->assertRedirect(route('admin.customers.show', $customer));
        $customer->refresh();
        $this->assertSame('New Name', $customer->business_name);
        $this->assertSame(CustomerStatus::Suspended, $customer->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.edited', 'resource_id' => (string) $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.suspended', 'resource_id' => (string) $customer->id]);
    }

    public function test_suspend_reactivate_and_archive_are_audited_without_deleting_customer(): void
    {
        $customer = Customer::factory()->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin);

        $this->post(route('admin.customers.suspend', $customer))->assertRedirect(route('admin.customers.show', $customer));
        $this->post(route('admin.customers.reactivate', $customer))->assertRedirect(route('admin.customers.show', $customer));
        $this->post(route('admin.customers.archive', $customer))->assertRedirect(route('admin.customers.show', $customer));

        $this->assertSame(CustomerStatus::Archived, $customer->fresh()->status);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.suspended', 'resource_id' => (string) $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.reactivated', 'resource_id' => (string) $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.archived', 'resource_id' => (string) $customer->id]);
    }

    public function test_support_role_can_view_customers_but_cannot_manage_them(): void
    {
        $support = User::factory()->withRole(AdminRole::Support)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($support)->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.customers.show', $customer))->assertOk();
        $this->get(route('admin.customers.create'))->assertForbidden();
        $this->get(route('admin.customers.edit', $customer))->assertForbidden();
        $this->post(route('admin.customers.store'), ['business_name' => 'Denied Ltd'])->assertForbidden();
        $this->put(route('admin.customers.update', $customer), ['business_name' => 'Denied Ltd'])->assertForbidden();
        $this->post(route('admin.customers.archive', $customer))->assertForbidden();
    }

    public function test_customer_detail_sections_render_and_archive_has_no_delete_route(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create());

        foreach (['overview', 'stores', 'devices', 'licence', 'activity', 'notes'] as $tab) {
            $this->get(route('admin.customers.show', ['customer' => $customer, 'tab' => $tab]))->assertOk();
        }

        $this->delete(route('admin.customers.show', $customer))->assertMethodNotAllowed();
    }

    public function test_archived_customer_cannot_be_reactivated_through_status_action(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Archived]);

        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->post(route('admin.customers.reactivate', $customer))
            ->assertStatus(409);
    }
}
