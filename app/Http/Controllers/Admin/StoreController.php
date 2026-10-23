<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Store;
use App\Services\HealthCheckInProgress;
use App\Services\LicenceLimitService;
use App\Services\PosEndpointHealthChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function store(StoreRequest $request, LicenceLimitService $limits): RedirectResponse
    {
        $this->authorize('create', Store::class);
        $data = $request->validated();
        $data['status'] ??= StoreStatus::Active->value;
        $data['max_devices'] ??= 10;

        $store = DB::transaction(function () use ($data, $request, $limits): Store {
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $customer->load('licence');
            $limit = $customer->licence?->max_stores;
            if ($limit !== null && $limits->storeCount($customer) >= $limit) {
                throw ValidationException::withMessages(['customer_id' => 'This customer has reached the maximum number of stores allowed by their licence.']);
            }

            $store = Store::query()->create($data);
            $this->recordAudit($store, 'store.created', $request, ['changed_fields' => array_keys($data)]);

            return $store;
        });

        return redirect()->route('admin.stores.show', $store)->with('status', 'Store created.');
    }

    public function show(Store $store, LicenceLimitService $limits): View
    {
        $this->authorize('view', $store);
        $store->load(['customer.licence', 'latestHealthCheck'])->loadCount(['devices as licence_devices_count' => fn ($query) => $query->where('status', '!=', 'revoked')]);
        $limitUsage = $limits->customerUsage($store->customer);
        $devices = $store->devices()->orderByDesc('registered_at')->limit(20)->get();
        $activity = AuditLog::query()->with('actor')
            ->where('resource_type', Store::class)
            ->where('resource_id', (string) $store->getKey())
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();

        return view('admin.stores.show', compact('store', 'devices', 'activity', 'limitUsage'));
    }

    public function edit(Store $store): View
    {
        $this->authorize('update', $store);
        $customers = Customer::query()->where('status', '!=', CustomerStatus::Archived)
            ->orWhere('id', $store->customer_id)
            ->orderBy('business_name')
            ->get();

        return view('admin.stores.edit', compact('store', 'customers'));
    }

    public function update(StoreRequest $request, Store $store, LicenceLimitService $limits): RedirectResponse
    {
        $this->authorize('update', $store);
        $data = $request->validated();
        $previousStatus = $store->status;
        $changedFields = collect($data)->filter(function ($value, string $field) use ($store): bool {
            $current = $store->getAttribute($field);
            $current = $current instanceof \BackedEnum ? $current->value : $current;

            return $current !== $value;
        })->keys()->values()->all();
        $before = [
            'name' => $store->name,
            'customer_id' => $store->customer_id,
            'store_code' => $store->store_code,
            'endpoint_host' => parse_url($store->endpoint_url, PHP_URL_HOST),
            'status' => $store->status->value,
            'max_devices' => $store->max_devices,
        ];
        $after = [
            'name' => $data['name'],
            'customer_id' => (int) $data['customer_id'],
            'store_code' => $data['store_code'],
            'endpoint_host' => parse_url($data['endpoint_url'], PHP_URL_HOST),
            'status' => $data['status'] ?? $store->status->value,
            'max_devices' => (int) ($data['max_devices'] ?? $store->max_devices),
        ];
        $safeChanges = [];
        foreach ($changedFields as $field) {
            $safeKey = $field === 'endpoint_url' ? 'endpoint_host' : $field;
            if (! array_key_exists($safeKey, $before)) {
                continue;
            }
            $safeChanges[$safeKey] = ['from' => $before[$safeKey], 'to' => $after[$safeKey]];
        }

        DB::transaction(function () use ($store, $data, $changedFields, $safeChanges, $previousStatus, $request, $limits): void {
            if ((int) $data['customer_id'] !== (int) $store->customer_id) {
                $customerIds = [(int) $store->customer_id, (int) $data['customer_id']];
                sort($customerIds);
                $lockedCustomers = Customer::query()->whereIn('id', $customerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $target = $lockedCustomers->get((int) $data['customer_id']);
                $target?->load('licence');
                $limit = $target?->licence?->max_stores;
                if ($target && $limit !== null && $limits->storeCount($target) >= $limit) {
                    throw ValidationException::withMessages(['customer_id' => 'The selected customer has reached the maximum number of stores allowed by their licence.']);
                }
            }

            $store->update($data);
            $this->recordAudit($store, 'store.edited', $request, ['changed_fields' => $changedFields, 'changes' => $safeChanges]);

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
        try {
            $check = $healthChecker->check($store);
        } catch (HealthCheckInProgress) {
            return redirect()->route('admin.stores.show', $store)->with('status', 'A health check is already running for this store.');
        }

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
