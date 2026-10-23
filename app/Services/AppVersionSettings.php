<?php

namespace App\Services;

use App\Models\SystemSetting;

class AppVersionSettings
{
    /** @return array{latest_version:?string,minimum_supported:?string,recommended:?string,download_url:?string,mandatory_update:bool,release_notes:?string} */
    public function forPlatform(string $platform): array
    {
        $configured = SystemSetting::query()->where('key', 'app_versions')->value('value');
        $version = is_array($configured) && is_array($configured[$platform] ?? null) ? $configured[$platform] : [];
        $latest = $this->safeVersion($version['latest_version'] ?? null);
        $minimum = $this->safeVersion($version['minimum_supported_version'] ?? null);

        if ($version === []) {
            $legacy = SystemSetting::query()->whereIn('key', ["minimum_{$platform}_version", "recommended_{$platform}_version"])
                ->get()->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value]);
            $minimum = $this->safeVersion($this->settingValue($legacy->get("minimum_{$platform}_version")));
            $latest = $this->safeVersion($this->settingValue($legacy->get("recommended_{$platform}_version")));
        }

        return [
            'latest_version' => $latest,
            'minimum_supported' => $minimum,
            'recommended' => $latest,
            'download_url' => $this->safeDownloadUrl($version['download_url'] ?? null),
            'mandatory_update' => (bool) ($version['mandatory_update'] ?? false),
            'release_notes' => $this->safeReleaseNotes($version['release_notes'] ?? null),
        ];
    }

    /** @return array{windows:array,android:array} */
    public function all(): array
    {
        return [
            'windows' => $this->forPlatform('windows'),
            'android' => $this->forPlatform('android'),
        ];
    }

    private function settingValue(mixed $value): mixed
    {
        return is_array($value) ? ($value['value'] ?? null) : $value;
    }

    private function safeVersion(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/D', $value) === 1 ? $value : null;
    }

    private function safeDownloadUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if (strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $value;
    }

    private function safeReleaseNotes(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';

        return $value === '' ? null : mb_substr($value, 0, 500);
    }
}
