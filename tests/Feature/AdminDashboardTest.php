<?php

namespace Tests\Feature;

use App\Enums\DevicePlatform;
use App\Enums\HealthCheckStatus;
use App\Enums\LicenceStatus;
use App\Enums\StoreStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Licence;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_can_view_dashboard_with_real_empty_states(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('No device activations yet')
            ->assertSee('No audit events recorded')
            ->assertSee('No stores are reporting offline');
    }

    public function test_dashboard_metrics_and_offline_list_reflect_database_records(): void
    {
        $customer = Customer::factory()->create(['business_name' => 'Pakasia Store']);
        $activeStore = Store::factory()->for($customer)->create(['name' => 'Town Centre', 'status' => StoreStatus::Active]);
        $offlineStore = Store::factory()->for($customer)->create(['name' => 'Market Road', 'status' => StoreStatus::Suspended]);
        Device::factory()->for($activeStore)->create([
            'name' => 'Front Counter',
            'platform' => DevicePlatform::Windows,
            'registered_at' => now()->subMinutes(5),
            'last_seen_at' => now(),
        ]);
        Licence::factory()->for($customer)->create(['status' => LicenceStatus::Active]);
        Licence::factory()->for(Customer::factory())->create(['status' => LicenceStatus::PastDue]);
        HealthCheck::factory()->for($offlineStore)->create([
            'status' => HealthCheckStatus::Offline,
            'checked_at' => now(),
        ]);
        AuditLog::factory()->create(['action' => 'device.registered', 'occurred_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics): bool => collect($metrics)->pluck('value', 'label')->all() === [
                'Customers' => 2,
                'Active stores' => 1,
                'Registered devices' => 1,
                'Devices seen today' => 1,
                'Active licences' => 1,
                'Past-due licences' => 1,
                'Suspended stores' => 1,
            ])
            ->assertSee('Customers')
            ->assertSee('Pakasia Store')
            ->assertSee('Front Counter')
            ->assertSee('Market Road')
            ->assertSee('Device Registered')
            ->assertSee('1 offline');
    }
}
