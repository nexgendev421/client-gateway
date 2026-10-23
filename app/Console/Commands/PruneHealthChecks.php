<?php

namespace App\Console\Commands;

use App\Models\HealthCheck;
use App\Services\RouterSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneHealthChecks extends Command
{
    protected $signature = 'router:prune-health-checks';

    protected $description = 'Remove old POS health history while retaining each store’s latest result';

    public function handle(RouterSettings $settings): int
    {
        $retentionDays = $settings->healthHistoryRetentionDays();
        $latestPerStore = HealthCheck::query()->selectRaw('MAX(id)')->groupBy('store_id');
        $deleted = DB::table('health_checks')
            ->where('checked_at', '<', now()->subDays($retentionDays))
            ->whereNotIn('id', $latestPerStore)
            ->delete();

        $this->info("Removed {$deleted} health checks older than {$retentionDays} days; latest result per store retained.");

        return self::SUCCESS;
    }
}
