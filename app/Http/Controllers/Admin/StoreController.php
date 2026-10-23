<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Store;
use App\Services\PosEndpointHealthChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Store::class);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($validated['q'] ?? ''));

        $stores = Store::query()
            ->with(['customer.licence', 'latestHealthCheck'])
            ->withCount('devices')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('store_code', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('business_name', 'like', "%{$search}%")
                            ->orWhere('trading_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.stores.index', compact('stores', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Store::class);
        $customers = Customer::query()->where('status', '!=', CustomerStatus::Archived)->orderBy('business_name')->get();

        return view('admin.stores.create', compact('customers'));
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Store::class);
        $data = $request->validated();
        $data['status'] ??= StoreStatus::Active->value;
        $data['max_devices'] ??= 10;

        $store = DB::transaction(function () use ($data, $request): Store {
            $store = Store::query()->create($data);
            $this->recordAudit($store, 'store.created', $request, ['changed_fields' => array_keys($data)]);

            return $store;
        });

        return redirect()->route('admin.stores.show', $store)->with('status', 'Store created.');
    }

    public function show(Store $store): View
    {
        $this->authorize('view', $store);
        $store->load(['customer.licence', 'latestHealthCheck'])->loadCount('devices');
        $devices = $store->devices()->orderByDesc('registered_at')->limit(20)->get();
        $activity = AuditLog::query()->with('actor')
            ->where('resource_type', Store::class)
            ->where('resource_id', (string) $store->getKey())
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();

        return view('admin.stores.show', compact('store', 'devices', 'activity'));
    }

    public function edit(Store $store): View
    {
        $this->authorize('update', $store);
        $customers = Customer::query()->where('status', '!=', CustomerStatus::Archived)
            ->orWhereKey($store->customer_id)
            ->orderBy('business_name')
            ->get();

        return view('admin.stores.edit', compact('store', 'customers'));
    }

    public function update(StoreRequest $request, Store $store): RedirectResponse
    {
        $this->authorize('update', $store);
        $data = $request->validated();
        $previousStatus = $store->status;
        $changedFields = collect($data)->filter(function ($value, string $field) use ($store): bool {
            $current = $store->getAttribute($field);
            $current = $current instanceof \BackedEnum ? $current->value : $current;

            return $current !== $value;
        })->keys()->values()->all();

        DB::transaction(function () use ($store, $data, $changedFields, $previousStatus, $request): void {
            $store->update($data);
            $this->recordAudit($store, 'store.edited', $request, ['changed_fields' => $changedFields]);

            if ($previousStatus !== $store->status) {
                $action = $store->status === StoreStatus::Suspended ? 'store.suspended' : 'store.reactivated';
                $this->recordAudit($store, $action, $request, [
                    'from_status' => $previousStatus->value,
                    'to_status' => $store->status->value,
                ]);
            }
        });

        return redirect()->route('admin.stores.show', $store)->with('status', 'Store updated.');
    }

    public function suspend(Request $request, Store $store): RedirectResponse
    {
        $this->authorize('changeStatus', $store);
        abort_unless($store->status === StoreStatus::Active, 409);

        DB::transaction(function () use ($store, $request): void {
            $store->update(['status' => StoreStatus::Suspended]);
            $this->recordAudit($store, 'store.suspended', $request, ['from_status' => 'active', 'to_status' => 'suspended']);
        });

        return redirect()->route('admin.stores.show', $store)->with('status', 'Store suspended.');
    }

    public function reactivate(Request $request, Store $store): RedirectResponse
    {
        $this->authorize('changeStatus', $store);
        abort_unless($store->status === StoreStatus::Suspended, 409);

        DB::transaction(function () use ($store, $request): void {
            $store->update(['status' => StoreStatus::Active]);
            $this->recordAudit($store, 'store.reactivated', $request, ['from_status' => 'suspended', 'to_status' => 'active']);
        });

        return redirect()->route('admin.stores.show', $store)->with('status', 'Store reactivated.');
    }

    public function testConnection(Request $request, Store $store, PosEndpointHealthChecker $healthChecker): RedirectResponse
    {
        $this->authorize('testConnection', $store);
        $check = $healthChecker->check($store);

        DB::transaction(function () use ($store, $check, $request): void {
            $this->recordAudit($store, 'store.connection_tested', $request, [
                'health_status' => $check->status->value,
                'response_time_ms' => $check->response_time_ms,
                'error_code' => $check->safe_error_code,
            ]);
        });

        $message = $check->status->value === 'online'
            ? 'Connection test succeeded.'
            : 'Connection test completed: '.$check->safe_error_message;

        return redirect()->route('admin.stores.show', $store)->with('status', $message);
    }

    private function recordAudit(Store $store, string $action, Request $request, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'resource_type' => Store::class,
            'resource_id' => (string) $store->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
