<?php

namespace App\Services;

use App\Models\SystemSetting;

class RouterSettings
{
    public function hasCentralSettings(): bool
    {
        return SystemSetting::query()->where('key', 'router_settings')->exists();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'router_settings')->first()?->value;
        $stored = is_array($stored) ? $stored : [];
        $expiry = (int) ($stored['default_activation_expiry_hours'] ?? 24);
        $uses = (int) ($stored['default_activation_max_uses'] ?? 1);
        $frequency = (int) ($stored['health_check_frequency_minutes'] ?? 5);
        $retention = (int) ($stored['health_history_retention_days'] ?? config('router.health_checks.retention_days', 30));
        $heartbeat = (int) ($stored['heartbeat_expected_interval_minutes'] ?? 5);
        $grace = (int) ($stored['default_licence_grace_days'] ?? 7);

        return [
            'default_activation_expiry_hours' => in_array($expiry, [1, 24, 168], true) ? $expiry : 24,
            'default_activation_max_uses' => min(100, max(1, $uses)),
            'health_check_frequency_minutes' => in_array($frequency, [5, 15, 30, 60], true) ? $frequency : 5,
            'health_history_retention_days' => min(3650, max(1, $retention)),
            'heartbeat_expected_interval_minutes' => min(1440, max(1, $heartbeat)),
            'default_licence_grace_days' => in_array($grace, [1, 3, 7, 14, 30, 60], true) ? $grace : 7,
            'support_email' => $this->safeEmail($stored['support_email'] ?? null),
            'support_phone' => $this->safePhone($stored['support_phone'] ?? null),
            'maintenance_notice' => $this->safeNotice($stored['maintenance_notice'] ?? null),
        ];
    }

    public function healthCheckFrequencyMinutes(): int
    {
        return $this->all()['health_check_frequency_minutes'];
    }

    public function healthHistoryRetentionDays(): int
    {
        return $this->all()['health_history_retention_days'];
    }

    public function heartbeatExpectedIntervalMinutes(): int
    {
        return $this->all()['heartbeat_expected_interval_minutes'];
    }

    public function maintenanceNotice(): ?string
    {
        return $this->all()['maintenance_notice'];
    }

    private function safeEmail(mixed $value): ?string
    {
        return is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false
            ? trim($value)
            : null;
    }

    private function safePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return preg_match('/^[0-9()+.\-\s]{1,30}$/D', $value) === 1 ? $value : null;
    }

    private function safeNotice(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';

        return $value === '' ? null : mb_substr($value, 0, 500);
    }
}
