<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\AppVersionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppVersionController extends Controller
{
    public function edit(AppVersionSettings $versions): View
    {
        $this->authorize('manage-router');

        return view('admin.settings.app-versions', ['versions' => $versions->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-router');
        $versionRule = ['required', 'string', 'max:40', 'regex:/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/D'];
        $data = $request->validate([
            'versions.windows.latest_version' => $versionRule,
            'versions.windows.minimum_supported_version' => $versionRule,
            'versions.windows.download_url' => ['required', 'url', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = is_string($value) ? parse_url($value) : false;
                if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                    $fail('The download link must be an HTTPS URL without embedded credentials.');
                }
            }],
            'versions.windows.mandatory_update' => ['required', 'boolean'],
            'versions.windows.release_notes' => ['nullable', 'string', 'max:500'],
            'versions.android.latest_version' => $versionRule,
            'versions.android.minimum_supported_version' => $versionRule,
            'versions.android.download_url' => ['required', 'url', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = is_string($value) ? parse_url($value) : false;
                if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                    $fail('The download link must be an HTTPS URL without embedded credentials.');
                }
            }],
            'versions.android.mandatory_update' => ['required', 'boolean'],
            'versions.android.release_notes' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['windows', 'android'] as $platform) {
            $latest = $data['versions'][$platform]['latest_version'];
            $minimum = $data['versions'][$platform]['minimum_supported_version'];
            if (version_compare($minimum, $latest, '>')) {
                throw ValidationException::withMessages([
                    "versions.{$platform}.minimum_supported_version" => 'The minimum supported version cannot be newer than the latest version.',
                ]);
            }
            $data['versions'][$platform]['mandatory_update'] = (bool) $data['versions'][$platform]['mandatory_update'];
            $data['versions'][$platform]['release_notes'] = isset($data['versions'][$platform]['release_notes'])
                ? mb_substr(trim(strip_tags($data['versions'][$platform]['release_notes'])), 0, 500)
                : null;
        }

        DB::transaction(function () use ($data, $request): void {
            $setting = SystemSetting::query()->updateOrCreate(['key' => 'app_versions'], ['value' => $data['versions']]);
            $metadata = [];
            foreach ($data['versions'] as $platform => $settings) {
                $metadata[$platform] = [
                    'latest_version' => $settings['latest_version'],
                    'minimum_supported_version' => $settings['minimum_supported_version'],
                    'download_host' => parse_url($settings['download_url'], PHP_URL_HOST),
                    'mandatory_update' => $settings['mandatory_update'],
                ];
            }

            AuditLog::query()->create([
                'actor_id' => $request->user()->getKey(),
                'action' => 'settings.app_versions_updated',
                'resource_type' => SystemSetting::class,
                'resource_id' => (string) $setting->getKey(),
                'metadata' => $metadata,
                'ip_address' => $request->ip(),
                'occurred_at' => now(),
            ]);
        });

        return redirect()->route('admin.settings.app-versions')->with('status', 'App version settings saved.');
    }
}
