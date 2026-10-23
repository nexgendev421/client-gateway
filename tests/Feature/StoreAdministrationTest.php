<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\CustomerStatus;
use App\Enums\HealthCheckStatus;
use App\Enums\LicenceStatus;
use App\Enums\StoreStatus;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Licence;
use App\Models\Store;
use App\Models\User;
use App\Services\HealthCheckInProgress;
use App\Services\PosEndpointException;
use App\Services\PosEndpointGuard;
use App\Services\PosEndpointHealthChecker;
use App\Services\PosHealthResponse;
use App\Services\PosHealthTransport;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class StoreAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_store_with_normalised_unique_code(): void
    {
        $customer = Customer::factory()->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $response = $this->actingAs($admin)->post(route('admin.stores.store'), $this->validStoreData($customer, [
            'name' => 'Huddersfield Store',
            'store_code' => ' asiaHud ',
        ]));

        $store = Store::query()->where('name', 'Huddersfield Store')->firstOrFail();
        $response->assertRedirect(route('admin.stores.show', $store));
        $this->assertSame('ASIAHUD', $store->store_code);
        $this->assertSame($store->id, Store::query()->whereStoreCode('asiaHud')->firstOrFail()->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.created', 'resource_id' => (string) $store->id]);

        $this->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer, ['store_code' => 'AsiaHud']))
            ->assertSessionHasErrors('store_code');
        $this->assertDatabaseCount('stores', 1);
    }

    public function test_database_enforces_case_insensitive_store_code_uniqueness(): void
    {
        $customer = Customer::factory()->create();
        Store::factory()->for($customer)->create(['store_code' => 'GREENHD01']);

        $this->expectException(QueryException::class);
        DB::table('stores')->insert([
            'customer_id' => $customer->id,
            'name' => 'Duplicate Code',
            'store_code' => 'greenhd01',
            'endpoint_url' => 'https://pos.example.com',
            'status' => 'active',
            'max_devices' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_create_and_update_validation_rejects_unsafe_endpoint_urls(): void
    {
        $customer = Customer::factory()->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin);

        $this->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer, ['endpoint_url' => 'http://localhost:3306']))
            ->assertSessionHasErrors('endpoint_url');
        $this->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer, ['endpoint_url' => 'https://127.0.0.1']))
            ->assertSessionHasErrors('endpoint_url');
        $this->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer, ['endpoint_url' => 'https://user:pass@pos.example.com?token=secret']))
            ->assertSessionHasErrors('endpoint_url');

        config(['app.env' => 'production']);
        $this->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer, ['endpoint_url' => 'http://pos.example.com']))
            ->assertSessionHasErrors('endpoint_url');

        $this->assertDatabaseCount('stores', 0);
    }

    public function test_store_edit_persists_notes_and_audits_status_change(): void
    {
        $customer = Customer::factory()->create();
        $store = Store::factory()->for($customer)->create(['store_code' => 'GREENHD01']);
        $oldEndpointHost = parse_url($store->endpoint_url, PHP_URL_HOST);
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->put(route('admin.stores.update', $store), $this->validStoreData($customer, [
            'name' => 'Green Street Shop',
            'store_code' => 'greenhd01',
            'endpoint_url' => 'https://new-pos.example.com',
            'status' => 'suspended',
            'notes' => 'Server moved to the new host.',
        ]))->assertRedirect(route('admin.stores.show', $store));

        $store->refresh();
        $this->assertSame('GREENHD01', $store->store_code);
        $this->assertSame('Server moved to the new host.', $store->notes);
        $this->assertSame(StoreStatus::Suspended, $store->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.edited', 'resource_id' => (string) $store->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.suspended', 'resource_id' => (string) $store->id]);
        $editEvent = AuditLog::query()->where('action', 'store.edited')->firstOrFail();
        $this->assertSame(['from' => $oldEndpointHost, 'to' => 'new-pos.example.com'], $editEvent->metadata['changes']['endpoint_host']);
        $this->assertStringNotContainsString('https://new-pos.example.com', json_encode($editEvent->metadata));
    }

    public function test_suspend_and_reactivate_are_audited(): void
    {
        $store = Store::factory()->create();
        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

        $this->post(route('admin.stores.suspend', $store))->assertRedirect(route('admin.stores.show', $store));
        $this->post(route('admin.stores.reactivate', $store))->assertRedirect(route('admin.stores.show', $store));

        $this->assertSame(StoreStatus::Active, $store->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.suspended', 'resource_id' => (string) $store->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.reactivated', 'resource_id' => (string) $store->id]);
    }

    public function test_test_connection_records_safe_health_result_and_audit(): void
    {
        $store = Store::factory()->create(['endpoint_url' => 'https://pos.example.com']);
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $guard = Mockery::mock(PosEndpointGuard::class);
        $guard->shouldReceive('validationError')->once()->andReturn(null);
        $guard->shouldReceive('resolvePublicAddresses')->once()->with('pos.example.com')->andReturn(['8.8.8.8']);
        $this->app->instance(PosEndpointGuard::class, $guard);
        $transport = Mockery::mock(PosHealthTransport::class);
        $transport->shouldReceive('get')->once()->with('https://pos.example.com/api/router/health', 'pos.example.com', 443, ['8.8.8.8'])
            ->andReturn(new PosHealthResponse(200, '{"status":"ok","pos_version":"4.2.0","api_version":"1"}', 43));
        $this->app->instance(PosHealthTransport::class, $transport);

        $this->actingAs($admin)->post(route('admin.stores.test-connection', $store))
            ->assertRedirect(route('admin.stores.show', $store))
            ->assertSessionHas('status', 'Connection test succeeded.');

        $health = HealthCheck::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(HealthCheckStatus::Online, $health->status);
        $this->assertSame('4.2.0', $health->pos_version);
        $this->assertSame('1', $health->api_version);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'store.connection_tested',
            'resource_id' => (string) $store->id,
        ]);
    }

    public function test_activation_code_is_hashed_one_use_and_not_audited_in_plaintext(): void
    {
        $store = Store::factory()->create(['store_code' => 'ASIAHUD']);
        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

        $response = $this->post(route('admin.stores.activation-codes.store', $store), [
            'expires_in_hours' => 24,
            'max_uses' => 1,
        ])->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private')->assertSee('Only the masked suffix is stored; the full code will not be shown again.');
        preg_match('/<code[^>]*>([^<]+)<\/code>/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $plainCode = html_entity_decode($matches[1]);

        $activation = ActivationCode::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $plainCode);
        $this->assertTrue(Hash::check(str_replace('-', '', $plainCode), $activation->code_hash));
        $this->assertSame(1, $activation->max_uses);
        $this->assertSame(0, $activation->used_count);
        $this->assertSame('active', $activation->status->value);
        $this->assertSame(substr($plainCode, -4), $activation->masked_suffix);
        $this->assertGreaterThanOrEqual(23, now()->diffInHours($activation->expires_at, false));
        $audit = AuditLog::query()->where('action', 'activation_code.generated')->firstOrFail();
        $this->assertStringNotContainsString($plainCode, json_encode($audit->metadata));
        $this->assertNotSame($plainCode, $activation->code_hash);
    }

    public function test_connection_check_blocks_private_dns_answers_without_sending_request(): void
    {
        $store = Store::factory()->create(['endpoint_url' => 'https://pos.example.com']);
        $guard = Mockery::mock(PosEndpointGuard::class);
        $guard->shouldReceive('validationError')->once()->andReturn(null);
        $guard->shouldReceive('resolvePublicAddresses')->once()->andThrow(new PosEndpointException(
            'ENDPOINT_ADDRESS_BLOCKED',
            'The endpoint resolves to a non-public network address.',
        ));
        $this->app->instance(PosEndpointGuard::class, $guard);
        $transport = Mockery::mock(PosHealthTransport::class);
        $transport->shouldNotReceive('get');
        $this->app->instance(PosHealthTransport::class, $transport);

        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->post(route('admin.stores.test-connection', $store))
            ->assertRedirect(route('admin.stores.show', $store));

        $this->assertDatabaseHas('health_checks', [
            'store_id' => $store->id,
            'status' => HealthCheckStatus::Offline->value,
            'safe_error_code' => 'ENDPOINT_ADDRESS_BLOCKED',
        ]);
    }

    public function test_health_checker_skips_a_store_with_an_existing_check_lock(): void
    {
        $store = Store::factory()->create();
        $lock = Cache::lock('pos-health-check:store:'.$store->id, 15);
        $this->assertTrue($lock->get());
        $transport = Mockery::mock(PosHealthTransport::class);
        $transport->shouldNotReceive('get');
        $guard = Mockery::mock(PosEndpointGuard::class);
        $guard->shouldNotReceive('validationError');

        try {
            app(PosEndpointHealthChecker::class)->check($store);
            $this->fail('Expected concurrent health check to be skipped.');
        } catch (HealthCheckInProgress) {
            $this->assertDatabaseCount('health_checks', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_health_check_does_not_follow_redirects_or_accept_unhealthy_payloads(): void
    {
        $store = Store::factory()->create(['endpoint_url' => 'https://pos.example.com']);
        $guard = Mockery::mock(PosEndpointGuard::class);
        $guard->shouldReceive('validationError')->twice()->andReturn(null);
        $guard->shouldReceive('resolvePublicAddresses')->twice()->andReturn(['8.8.8.8']);
        $this->app->instance(PosEndpointGuard::class, $guard);
        $transport = Mockery::mock(PosHealthTransport::class);
        $transport->shouldReceive('get')->twice()->andReturn(
            new PosHealthResponse(302, '{"status":"ok"}', 12),
            new PosHealthResponse(200, '{"status":"degraded","pos_version":"2.0"}', 14),
        );
        $this->app->instance(PosHealthTransport::class, $transport);
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->post(route('admin.stores.test-connection', $store))->assertRedirect();
        $this->post(route('admin.stores.test-connection', $store))->assertRedirect();

        $checks = HealthCheck::query()->where('store_id', $store->id)->orderBy('id')->get();
        $this->assertCount(2, $checks);
        $this->assertSame('REDIRECT_BLOCKED', $checks[0]->safe_error_code);
        $this->assertSame('INVALID_STATUS', $checks[1]->safe_error_code);
        $this->assertSame(HealthCheckStatus::Offline, $checks[0]->status);
        $this->assertNull($checks[1]->pos_version);
    }

    public function test_endpoint_guard_rejects_loopback_link_local_and_private_dns_answers(): void
    {
        $guard = app(PosEndpointGuard::class);
        foreach (['127.0.0.1', '169.254.10.20', '10.20.30.40', '192.168.1.10', '::1', 'fc00::1', 'fe80::1'] as $address) {
            try {
                $guard->assertPublicAddresses([$address]);
                $this->fail('Expected non-public address to be blocked: '.$address);
            } catch (PosEndpointException $exception) {
                $this->assertSame('ENDPOINT_ADDRESS_BLOCKED', $exception->safeCode);
            }
        }
    }

    public function test_support_can_manage_store_operations_but_not_sensitive_router_settings(): void
    {
        $store = Store::factory()->create();
        $this->actingAs(User::factory()->withRole(AdminRole::Support)->create());

        $this->get(route('admin.stores.index'))->assertOk();
        $this->get(route('admin.stores.show', $store))->assertOk();
        $this->get(route('admin.stores.create'))->assertOk();
        $this->get(route('admin.stores.edit', $store))->assertOk();
        $this->post(route('admin.stores.activation-codes.store', $store), ['expires_in_hours' => 24, 'max_uses' => 1])->assertOk();
        $this->post(route('admin.stores.suspend', $store))->assertRedirect(route('admin.stores.show', $store));
        $this->get(route('admin.admin-users.index'))->assertForbidden();
        $this->get(route('admin.settings.app-versions'))->assertForbidden();
    }

    public function test_store_detail_shows_required_live_relationship_data_and_has_no_delete_route(): void
    {
        $customer = Customer::factory()->create(['business_name' => 'Green Foods']);
        $store = Store::factory()->for($customer)->create(['name' => 'Central Shop', 'store_code' => 'GREENHD01']);
        Device::factory()->for($store)->create(['name' => 'Counter 1']);
        Licence::factory()->for($customer)->create(['plan' => 'standard', 'status' => LicenceStatus::Active]);
        HealthCheck::factory()->for($store)->create([
            'status' => HealthCheckStatus::Online,
            'pos_version' => '7.4.0',
            'api_version' => 'v1',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.stores.show', $store))
            ->assertOk()
            ->assertSee('Green Foods')
            ->assertSee('Central Shop')
            ->assertSee('GREENHD01')
            ->assertSee('>1<span', false)
            ->assertSee('/ 10</span>', false)
            ->assertSee('Counter 1')
            ->assertSee('7.4.0')
            ->assertSee('v1')
            ->assertSee('active · standard')
            ->assertSee('Online');

        $this->delete(route('admin.stores.show', $store))->assertMethodNotAllowed();
    }

    public function test_archived_customer_cannot_receive_new_store(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Archived]);

        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer))
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('stores', 0);
    }

    public function test_store_creation_enforces_customer_licence_store_limit(): void
    {
        $customer = Customer::factory()->create();
        Licence::factory()->for($customer)->create(['max_stores' => 1]);
        Store::factory()->for($customer)->create();

        $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create())
            ->from(route('admin.stores.create'))
            ->post(route('admin.stores.store'), $this->validStoreData($customer))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(1, $customer->stores()->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'store.created']);
    }

    public function test_store_can_be_moved_only_when_target_customer_has_store_capacity(): void
    {
        $source = Customer::factory()->create();
        $target = Customer::factory()->create();
        Licence::factory()->for($target)->create(['max_stores' => 1]);
        $store = Store::factory()->for($source)->create();
        Store::factory()->for($target)->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->from(route('admin.stores.edit', $store))
            ->put(route('admin.stores.update', $store), $this->validStoreData($target))
            ->assertSessionHasErrors('customer_id');
        $this->assertSame($source->id, $store->fresh()->customer_id);
    }

    public function test_customer_limit_usage_shows_existing_over_limit_resources_without_disabling_them(): void
    {
        $customer = Customer::factory()->create();
        Licence::factory()->for($customer)->create(['max_stores' => 1, 'max_devices' => 1]);
        $store = Store::factory()->for($customer)->create(['max_devices' => 5]);
        Device::factory()->for($store)->count(2)->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

        $this->actingAs($admin)->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'licence']))
            ->assertOk()->assertSee('Devices in use')->assertSee('2 / 1')->assertSee('Over limit')
            ->assertSee('Existing stores and devices remain available');
        $this->get(route('admin.stores.show', $store))->assertOk()->assertSee('Device capacity');
        $this->assertDatabaseCount('devices', 2);
    }

    private function validStoreData(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'name' => 'Test Store',
            'store_code' => 'TESTHD01',
            'endpoint_url' => 'https://pos.example.com',
            'max_devices' => 10,
            'status' => 'active',
            'notes' => null,
        ], $overrides);
    }
}
