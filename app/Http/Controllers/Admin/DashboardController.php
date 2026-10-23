<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HealthCheckStatus;
use App\Enums\LicenceStatus;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Licence;
use App\Models\Store;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->startOfDay();

        return view('admin.dashboard', [
            'metrics' => [
                ['label' => 'Customers', 'value' => Customer::count(), 'tone' => 'blue'],
                ['label' => 'Active stores', 'value' => Store::where('status', StoreStatus::Active)->count(), 'tone' => 'cyan'],
                ['label' => 'Registered devices', 'value' => Device::count(), 'tone' => 'slate'],
                ['label' => 'Devices seen today', 'value' => Device::whereDate('last_seen_at', $today)->count(), 'tone' => 'cyan'],
                ['label' => 'Active licences', 'value' => Licence::where('status', LicenceStatus::Active)->count(), 'tone' => 'blue'],
                ['label' => 'Past-due licences', 'value' => Licence::where('status', LicenceStatus::PastDue)->count(), 'tone' => 'amber'],
                ['label' => 'Suspended stores', 'value' => Store::where('status', StoreStatus::Suspended)->count(), 'tone' => 'rose'],
            ],
            'recentActivations' => Device::query()
                ->with('store.customer')
                ->whereNotNull('registered_at')
                ->orderByDesc('registered_at')
                ->limit(6)
                ->get(),
            'recentAuditEvents' => AuditLog::query()
                ->with('actor')
                ->orderByDesc('occurred_at')
                ->limit(6)
                ->get(),
            'offlineStores' => Store::query()
                ->with(['customer', 'latestHealthCheck'])
                ->whereHas('latestHealthCheck', fn ($query) => $query->where('status', HealthCheckStatus::Offline))
                ->orderBy('name')
                ->limit(6)
                ->get(),
        ]);
    }
}
