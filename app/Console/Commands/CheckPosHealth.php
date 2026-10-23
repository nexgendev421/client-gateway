<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\HealthCheckInProgress;
use App\Services\PosEndpointHealthChecker;
use App\Services\RouterSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckPosHealth extends Command
{
    protected $signature = 'router:check-pos-health {--scheduled : Apply the configured health-check interval}';

    protected $description = 'Check active customer POS endpoints using the safe health API';

    public function handle(PosEndpointHealthChecker $healthChecker, RouterSettings $settings): int
    {
        if ($this->option('scheduled')) {
            $interval = $settings->healthCheckFrequencyMinutes();
            if (! Cache::add('router:scheduled-health-check:'.$interval, now()->timestamp, $interval * 60)) {
                $this->info('Health check interval has not elapsed; skipped.');

                return self::SUCCESS;
            }
        }

        $checked = 0;
        $skipped = 0;

        Store::query()->where('status', 'active')->orderBy('id')->chunkById(100, function ($stores) use ($healthChecker, &$checked, &$skipped): void {
            foreach ($stores as $store) {
                try {
                    $healthChecker->check($store);
                    $checked++;
                } catch (HealthCheckInProgress) {
                    $skipped++;
                }
            }
        });

        $this->info("Health checks completed: {$checked}; skipped because already running: {$skipped}.");

        return self::SUCCESS;
    }
}
