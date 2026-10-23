<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\HealthCheckInProgress;
use App\Services\PosEndpointHealthChecker;
use Illuminate\Console\Command;

class CheckPosHealth extends Command
{
    protected $signature = 'router:check-pos-health';

    protected $description = 'Check active customer POS endpoints using the safe health API';

    public function handle(PosEndpointHealthChecker $healthChecker): int
    {
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
