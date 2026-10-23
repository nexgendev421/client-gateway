<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\LicenceLimitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($validated['q'] ?? ''));

        $customers = Customer::query()
            ->with('licence')
            ->withCount(['stores', 'devices'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('business_name', 'like', "%{$search}%")
                        ->orWhere('trading_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('business_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('admin.customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $data = $request->validated();
        $data['status'] ??= CustomerStatus::Active->value;

        $customer = DB::transaction(function () use ($data, $request): Customer {
            $customer = Customer::query()->create($data);
            $this->recordAudit($customer, 'customer.created', $request, ['changed_fields' => array_keys($data)]);

            return $customer;
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer created.');
    }

    public function show(Request $request, Customer $customer, LicenceLimitService $limits): View
    {
        $this->authorize('view', $customer);
        $customer->loadCount(['stores', 'devices']);
        $customer->load('licence');
        $limitUsage = $limits->customerUsage($customer);
        $tab = $request->query('tab', 'overview');
        $tabs = ['overview', 'stores', 'devices', 'licence', 'activity', 'notes'];

        if (! in_array($tab, $tabs, true)) {
            $tab = 'overview';
        }

        $stores = $tab === 'stores'
            ? $customer->stores()->with('latestHealthCheck')->withCount(['devices as licence_devices_count' => fn ($query) => $query->where('status', '!=', 'revoked')])->orderBy('name')->paginate(20)->withQueryString()
            : collect();
        $devices = $tab === 'devices'
            ? $customer->devices()->with('store')->orderBy('name')->paginate(20)->withQueryString()
            : collect();
        $activity = $tab === 'activity'
            ? AuditLog::query()->with('actor')
                ->where('resource_type', Customer::class)
                ->where('resource_id', (string) $customer->getKey())
                ->orderByDesc('occurred_at')
                ->limit(50)
                ->get()
            : collect();

        return view('admin.customers.show', compact('customer', 'tab', 'stores', 'devices', 'activity', 'limitUsage'));
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $data = $request->validated();
        $previousStatus = $customer->status;
        $changedFields = collect($data)->filter(function ($value, string $field) use ($customer): bool {
            $current = $customer->getAttribute($field);
            $current = $current instanceof \BackedEnum ? $current->value : $current;

            return $current !== $value;
        })->keys()->values()->all();

        DB::transaction(function () use ($customer, $data, $changedFields, $previousStatus, $request): void {
            $customer->update($data);
            $this->recordAudit($customer, 'customer.edited', $request, ['changed_fields' => $changedFields]);

            if ($previousStatus !== $customer->status) {
                $action = $customer->status === CustomerStatus::Suspended
                    ? 'customer.suspended'
                    : 'customer.reactivated';
                $this->recordAudit($customer, $action, $request, [
                    'from_status' => $previousStatus->value,
                    'to_status' => $customer->status->value,
                ]);
            }
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function suspend(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('changeStatus', $customer);
        abort_unless($customer->status === CustomerStatus::Active, 409);

        DB::transaction(function () use ($customer, $request): void {
            $customer->update(['status' => CustomerStatus::Suspended]);
            $this->recordAudit($customer, 'customer.suspended', $request, ['from_status' => 'active', 'to_status' => 'suspended']);
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer suspended.');
    }

    public function reactivate(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('changeStatus', $customer);
        abort_unless($customer->status === CustomerStatus::Suspended, 409);

        DB::transaction(function () use ($customer, $request): void {
            $customer->update(['status' => CustomerStatus::Active]);
            $this->recordAudit($customer, 'customer.reactivated', $request, ['from_status' => 'suspended', 'to_status' => 'active']);
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer reactivated.');
    }

    public function archive(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('archive', $customer);
        abort_unless($customer->status !== CustomerStatus::Archived, 409);

        DB::transaction(function () use ($customer, $request): void {
            $previousStatus = $customer->status->value;
            $customer->update(['status' => CustomerStatus::Archived]);
            $this->recordAudit($customer, 'customer.archived', $request, ['from_status' => $previousStatus, 'to_status' => 'archived']);
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer archived.');
    }

    private function recordAudit(Customer $customer, string $action, Request $request, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'resource_type' => Customer::class,
            'resource_id' => (string) $customer->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
