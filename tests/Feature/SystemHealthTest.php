<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\HealthCheckStatus;
use App\Models\Customer;
use App\Models\HealthCheck;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_page_shows_latest_store_status_and_check_history(): void
    {
        $customer = Customer::factory()->create(['business_name' => 'Green Foods']);
        $onlineStore = Store::factory()->for($customer)->create(['name' => 'Central Shop']);
        $offlineStore = Store::factory()->create(['name' => 'North Shop']);
        $uncheckedStore = Store::factory()->create(['name' => 'New Shop']);
        HealthCheck::factory()->for($onlineStore)->create(['status' => HealthCheckStatus::Online, 'response_time_ms' => 42]);
        HealthCheck::factory()->for($offlineStore)->create(['status' => HealthCheckStatus::Offline, 'safe_error_code' => 'TIMEOUT', 'safe_error_message' => 'The endpoint request timed out.']);

        $this->actingAs(User::factory()->withRole(AdminRole::Support)->create())
            ->get(route('admin.system-health.index'))
            ->assertOk()
            ->assertSee('Green Foods')
            ->assertSee('Central Shop')
            ->assertSee('North Shop')
            ->assertSee('New Shop')
            ->assertSee('Online')
            ->assertSee('Offline')
            ->assertSee('Not checked')
            ->assertSee('42 ms')
            ->assertSee('The endpoint request timed out.')
            ->assertSee('Automated endpoint checks run every 5 minutes');
    }

    public function test_system_health_requires_authenticated_admin(): void
    {
        $this->get(route('admin.system-health.index'))->assertRedirect(route('login'));
    }
}
