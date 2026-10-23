<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\AppVersionSettings;
use App\Services\RouterSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RouterSettingsController extends Controller
{
    public function edit(RouterSettings $settings, AppVersionSettings $versions): View
    {
        $this->authorize('manage-router');

        return view('admin.settings.index', [
            'settings' => $settings->all(),
            'versions' => $versions->all(),
            'activeNav' => 'settings',
        ]);
    }

    public function update(Request $request, RouterSettings $settings): RedirectResponse
    {
        $this->authorize('manage-router');
        $data = $request->validate([
            'default_activation_expiry_hours' => ['required', 'integer', 'in:1,24,168'],
            'default_activation_max_uses' => ['required', 'integer', 'min:1', 'max:100'],
            'health_check_frequency_minutes' => ['required', 'integer', 'in:5,15,30,60'],
            'health_history_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'heartbeat_expected_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'default_licence_grace_days' => ['required', 'integer', 'in:1,3,7,14,30,60'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+.\-\s]*$/D'],
            'maintenance_notice' => ['nullable', 'string', 'max:500'],
        ]);
        foreach ([
            'default_activation_expiry_hours',
            'default_activation_max_uses',
            'health_check_frequency_minutes',
            'health_history_retention_days',
            'heartbeat_expected_interval_minutes',
            'default_licence_grace_days',
        ] as $field) {
            $data[$field] = (int) $data[$field];
        }
        $data['support_email'] = isset($data['support_email']) ? trim($data['support_email']) : null;
        $data['support_phone'] = isset($data['support_phone']) ? trim($data['support_phone']) : null;
        $data['maintenance_notice'] = isset($data['maintenance_notice'])
            ? mb_substr(trim(strip_tags($data['maintenance_notice'])), 0, 500)
            : null;
        $before = $settings->all();
        $changed = array_values(array_filter(array_keys($data), fn (string $key): bool => $before[$key] !== $data[$key]));

        DB::transaction(function () use ($request, $data, $changed): void {
            $setting = SystemSetting::query()->updateOrCreate(['key' => 'router_settings'], ['value' => $data]);
            AuditLog::query()->create([
                'actor_id' => $request->user()->getKey(),
                'action' => 'settings.router_updated',
                'resource_type' => SystemSetting::class,
                'resource_id' => (string) $setting->getKey(),
                'metadata' => ['changed_settings' => $changed],
                'ip_address' => $request->ip(),
                'occurred_at' => now(),
            ]);
        });
        foreach ([5, 15, 30, 60] as $interval) {
            Cache::forget('router:scheduled-health-check:'.$interval);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Router settings saved.');
    }
}
