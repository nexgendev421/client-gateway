<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SystemSetting;

class DeviceConfigurationService
{
    private const SETTING_KEYS = [
        'minimum_windows_version',
        'recommended_windows_version',
        'minimum_android_version',
        'recommended_android_version',
        'maintenance_notice',
    ];

    /** @return array<string, mixed> */
    public function forDevice(Device $device): array
    {
        $device->loadMissing('store.customer.licence');
        $store = $device->store;
        $customer = $store->customer;
        $licence = $customer->licence;
        $settings = SystemSetting::query()->whereIn('key', self::SETTING_KEYS)->get()->mapWithKeys(
            fn (SystemSetting $setting): array => [
                $setting->key => $setting->key === 'maintenance_notice'
                    ? $this->plainSettingValue($setting->value)
                    : $this->settingValue($setting->value),
            ],
        );
        $platform = $device->platform->value;

        return [
            'business_name' => $customer->trading_name ?: $customer->business_name,
            'store_name' => $store->name,
            'endpoint_url' => $store->endpoint_url,
            'device' => [
                'reference' => $device->public_id,
                'name' => $device->name,
                'status' => $device->status->value,
            ],
            'licence' => $licence === null ? null : [
                'plan' => $licence->plan,
                'status' => $licence->status->value,
                'valid_until' => $licence->valid_until?->toIso8601String(),
                'grace_until' => $licence->grace_until?->toIso8601String(),
            ],
            'app_versions' => [
                'platform' => $platform,
                'minimum_supported' => $settings->get('minimum_'.$platform.'_version'),
                'recommended' => $settings->get('recommended_'.$platform.'_version'),
            ],
            'maintenance_notice' => $this->maintenanceNotice($settings->get('maintenance_notice')),
        ];
    }

    private function settingValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,31}$/D', $value) === 1 ? $value : null;
    }

    private function plainSettingValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }

        return is_string($value) ? trim($value) : null;
    }

    private function maintenanceNotice(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 500);
    }
}
