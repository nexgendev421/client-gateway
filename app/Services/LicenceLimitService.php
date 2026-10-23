<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Store;

class LicenceLimitService
{
    public function storeCount(Customer $customer): int
    {
        return $customer->stores()->count();
    }

    public function customerDeviceCount(Customer $customer): int
    {
        return Device::query()
            ->whereHas('store', fn ($query) => $query->where('customer_id', $customer->getKey()))
            ->where('status', '!=', DeviceStatus::Revoked->value)
            ->count();
    }

    public function storeDeviceCount(Store $store): int
    {
        return $store->devices()->where('status', '!=', DeviceStatus::Revoked->value)->count();
    }

    public function customerUsage(Customer $customer): array
    {
        $customer->loadMissing('licence');
        $stores = $this->storeCount($customer);
        $devices = $this->customerDeviceCount($customer);
        $storeLimit = $customer->licence?->max_stores;
        $deviceLimit = $customer->licence?->max_devices;

        return [
            'stores' => $stores,
            'store_limit' => $storeLimit,
            'stores_over_limit' => $storeLimit !== null && $stores > $storeLimit,
            'stores_at_limit' => $storeLimit !== null && $stores >= $storeLimit,
            'devices' => $devices,
            'device_limit' => $deviceLimit,
            'devices_over_limit' => $deviceLimit !== null && $devices > $deviceLimit,
            'devices_at_limit' => $deviceLimit !== null && $devices >= $deviceLimit,
        ];
    }
}
