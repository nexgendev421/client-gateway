<?php

namespace Tests\Feature;

use App\Enums\HealthCheckStatus;
use App\Enums\StoreStatus;
use App\Models\HealthCheck;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PosEndpointHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class HealthMonitoringCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_automated_command_checks_active_stores_in_a_bounded_sequential_run(): void
    {
        $active = Store::factory()->count(2)->create();
        Store::factory()->create(['status' => StoreStatus::Suspended]);
        $checker = Mockery::mock(PosEndpointHealthChecker::class);
        $checker->shouldReceive('check')->twice()->andReturnUsing(
            fn (Store $store) => HealthCheck::factory()->for($store)->create(),
        );
        $this->app->instance(PosEndpointHealthChecker::class, $checker);

        $this->artisan('router:check-pos-health')->expectsOutput('Health checks completed: 2; skipped because already running: 0.')
            ->assertExitCode(0);

        $this->assertSame(2, HealthCheck::query()->count());
        $this->assertSame(2, $active->count());
    }

    public function test_scheduled_health_command_obeys_configured_interval(): void
    {
        config(['cache.default' => 'file']);
        Store::factory()->create();
        SystemSetting::query()->create(['key' => 'router_settings', 'value' => ['health_check_frequency_minutes' => 15]]);
        Cache::store('file')->forget('router:scheduled-health-check:15');
        $checker = Mockery::mock(PosEndpointHealthChecker::class);
        $checker->shouldReceive('check')->once()->andReturnUsing(
            fn (Store $store) => HealthCheck::factory()->for($store)->create(),
        );
        $this->app->instance(PosEndpointHealthChecker::class, $checker);

        $this->artisan('router:check-pos-health', ['--scheduled' => true])->assertExitCode(0);
        $this->assertTrue(Cache::store('file')->has('router:scheduled-health-check:15'));
        $this->assertFalse(Cache::store('file')->add('router:scheduled-health-check:15', now()->timestamp, 900));
        $this->artisan('router:check-pos-health', ['--scheduled' => true])->assertExitCode(0);
        Cache::store('file')->forget('router:scheduled-health-check:15');
    }

    public function test_history_pruning_keeps_the_latest_result_per_store(): void
    {
        config(['router.health_checks.retention_days' => 30]);
        $store = Store::factory()->create();
        $old = HealthCheck::factory()->for($store)->create(['checked_at' => now()->subDays(60)]);
        $latest = HealthCheck::factory()->for($store)->create(['checked_at' => now()->subDays(45), 'status' => HealthCheckStatus::Offline]);
        $freshStore = Store::factory()->create();
        $fresh = HealthCheck::factory()->for($freshStore)->create(['checked_at' => now()->subDays(10)]);

        $this->artisan('router:prune-health-checks')->expectsOutput('Removed 1 health checks older than 30 days; latest result per store retained.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('health_checks', ['id' => $old->id]);
        $this->assertDatabaseHas('health_checks', ['id' => $latest->id]);
        $this->assertDatabaseHas('health_checks', ['id' => $fresh->id]);
    }

    public function test_history_pruning_uses_central_retention_setting(): void
    {
        $store = Store::factory()->create();
        $historical = HealthCheck::factory()->for($store)->create(['checked_at' => now()->subDays(20)]);
        HealthCheck::factory()->for($store)->create(['checked_at' => now()->subDays(10)]);
        SystemSetting::query()->create(['key' => 'router_settings', 'value' => ['health_history_retention_days' => 7]]);

        $this->artisan('router:prune-health-checks')
            ->expectsOutput('Removed 1 health checks older than 7 days; latest result per store retained.')
            ->assertExitCode(0);
        $this->assertDatabaseMissing('health_checks', ['id' => $historical->id]);
    }

    public function test_system_health_shows_last_success_after_a_later_failed_check(): void
    {
        $store = Store::factory()->create();
        $successfulAt = now()->subHour()->startOfSecond();
        HealthCheck::factory()->for($store)->create([
            'checked_at' => $successfulAt,
            'status' => HealthCheckStatus::Online,
            'pos_version' => '1.7.2',
            'api_version' => '1',
        ]);
        HealthCheck::factory()->for($store)->create([
            'checked_at' => now()->subMinutes(2),
            'status' => HealthCheckStatus::Offline,
            'pos_version' => null,
            'api_version' => null,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.system-health.index'))
            ->assertOk()->assertSee('Automated endpoint checks run every 5 minutes')
            ->assertSee('POS / API')->assertSee('1.7.2 / 1')->assertSee($successfulAt->format('j M Y, H:i:s'));
    }
}
