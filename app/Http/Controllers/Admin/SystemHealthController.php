<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HealthCheckStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthCheck;
use App\Models\Store;
use App\Services\RouterSettings;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function __invoke(RouterSettings $settings): View
    {
        $this->authorize('viewAny', Store::class);

        $stores = Store::query()->with(['customer', 'latestHealthCheck', 'latestSuccessfulHealthCheck'])->orderBy('name')->paginate(25);
        $recentChecks = HealthCheck::query()->with(['store.customer'])->orderByDesc('checked_at')->limit(20)->get();
        $onlineCount = Store::query()->whereHas('latestHealthCheck', fn ($query) => $query->where('status', HealthCheckStatus::Online))->count();
        $offlineCount = Store::query()->whereHas('latestHealthCheck', fn ($query) => $query->where('status', HealthCheckStatus::Offline))->count();
        $uncheckedCount = Store::query()->whereDoesntHave('healthChecks')->count();

        return view('admin.system-health.index', compact('stores', 'recentChecks', 'onlineCount', 'offlineCount', 'uncheckedCount') + [
            'healthCheckFrequencyMinutes' => $settings->healthCheckFrequencyMinutes(),
        ]);
    }
}
