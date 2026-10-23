<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Support\Collection;

class OutdatedDeviceService
{
    public function __construct(private readonly AppVersionSettings $versions) {}

    /** @return array{count:int,devices:Collection<int,Device>} */
    public function summary(int $limit = 6): array
    {
        $latest = [
            'windows' => $this->versions->forPlatform('windows')['latest_version'],
            'android' => $this->versions->forPlatform('android')['latest_version'],
        ];
        if ($latest['windows'] === null && $latest['android'] === null) {
            return ['count' => 0, 'devices' => collect()];
        }

        $count = 0;
        $ids = [];
        Device::query()->where('status', DeviceStatus::Active)->orderBy('id')
            ->chunkById(500, function ($devices) use ($latest, $limit, &$count, &$ids): void {
                foreach ($devices as $device) {
                    $latestVersion = $latest[$device->platform->value] ?? null;
                    if ($latestVersion === null || ($device->app_version !== null && version_compare((string) $device->app_version, $latestVersion, '>='))) {
                        continue;
                    }
                    $count++;
                    if (count($ids) < $limit) {
                        $ids[] = $device->getKey();
                    }
                }
            });

        $devices = $ids === [] ? collect() : Device::query()->with('store.customer')->whereIn('id', $ids)->orderBy('id')->get();

        return ['count' => $count, 'devices' => $devices];
    }
}
