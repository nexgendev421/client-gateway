<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Device::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'platform' => ['nullable', Rule::in(['windows', 'android'])],
            'status' => ['nullable', Rule::in(['active', 'disabled', 'revoked'])],
            'seen' => ['nullable', Rule::in(['recent', 'offline'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $offlineSince = now()->subDay();

        $devices = Device::query()->with(['store.customer'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('public_id', 'like', "%{$search}%")
                        ->orWhereHas('store', fn (Builder $stores) => $stores->where('name', 'like', "%{$search}%")
                            ->orWhere('store_code', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn (Builder $customers) => $customers->where('business_name', 'like', "%{$search}%")
                                ->orWhere('trading_name', 'like', "%{$search}%")));
                });
            })
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->whereHas('store', fn (Builder $stores) => $stores->where('customer_id', $filters['customer_id'])))
            ->when(isset($filters['store_id']), fn (Builder $query) => $query->where('store_id', $filters['store_id']))
            ->when(isset($filters['platform']), fn (Builder $query) => $query->where('platform', $filters['platform']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['seen'] ?? null) === 'recent', fn (Builder $query) => $query->where('last_seen_at', '>=', $offlineSince))
            ->when(($filters['seen'] ?? null) === 'offline', fn (Builder $query) => $query->where(fn (Builder $seen) => $seen->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $offlineSince)))
            ->orderBy('name')->paginate(25)->withQueryString();

        $customers = Customer::query()->orderBy('business_name')->get(['id', 'business_name', 'trading_name']);
        $stores = Store::query()->with('customer')->orderBy('name')->get(['id', 'customer_id', 'name', 'store_code']);

        return view('admin.devices.index', compact('devices', 'filters', 'search', 'customers', 'stores'));
    }

    public function show(Device $device): View
    {
        $this->authorize('view', $device);
        $device->load(['store.customer.licence', 'credentials']);
        $stores = Store::query()->where('customer_id', $device->store->customer_id)->orderBy('name')->get();
        $activity = AuditLog::query()->with('actor')
            ->where('resource_type', Device::class)
            ->where('resource_id', (string) $device->getKey())
            ->orderByDesc('occurred_at')->limit(20)->get();

        return view('admin.devices.show', compact('device', 'stores', 'activity'));
    }

    public function rename(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('manage', $device);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        DB::transaction(function () use ($device, $data, $request): void {
            $locked = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            abort_if($locked->status === DeviceStatus::Revoked, 409);
            $before = $locked->name;
            $locked->update(['name' => trim($data['name'])]);
            $this->audit($locked, 'device.renamed', $request, ['previous_name' => $before, 'name' => $locked->name]);
        });

        return redirect()->route('admin.devices.show', $device)->with('status', 'Device renamed.');
    }

    public function move(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('manage', $device);
        $customerId = $device->store->customer_id;
        $data = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('customer_id', $customerId)],
        ]);
        $destination = Store::query()->findOrFail($data['store_id']);
        $sourceId = $device->store_id;
        if ($sourceId === $destination->id) {
            return redirect()->route('admin.devices.show', $device)->with('status', 'Device is already assigned to that store.');
        }

        DB::transaction(function () use ($device, $destination, $sourceId, $request, $customerId): void {
            $locked = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            abort_if($locked->status === DeviceStatus::Revoked, 409);
            abort_unless($locked->store->customer_id === $customerId, 409);
            $locked->update(['store_id' => $destination->id]);
            $this->audit($locked, 'device.moved', $request, [
                'from_store_id' => $sourceId,
                'to_store_id' => $destination->id,
            ]);
        });

        return redirect()->route('admin.devices.show', $device)->with('status', 'Device moved to '.$destination->name.'.');
    }

    public function disable(Request $request, Device $device): RedirectResponse
    {
        return $this->changeStatus($request, $device, DeviceStatus::Active, DeviceStatus::Disabled, 'device.disabled', 'Device disabled.');
    }

    public function reactivate(Request $request, Device $device): RedirectResponse
    {
        return $this->changeStatus($request, $device, DeviceStatus::Disabled, DeviceStatus::Active, 'device.reactivated', 'Device reactivated.');
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        return $this->changeStatus($request, $device, [DeviceStatus::Active, DeviceStatus::Disabled], DeviceStatus::Revoked, 'device.revoked', 'Device permanently revoked.');
    }

    private function changeStatus(Request $request, Device $device, DeviceStatus|array $from, DeviceStatus $to, string $action, string $message): RedirectResponse
    {
        $this->authorize('manage', $device);
        $allowed = is_array($from) ? $from : [$from];

        DB::transaction(function () use ($device, $request, $allowed, $to, $action): void {
            $locked = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            abort_unless(in_array($locked->status, $allowed, true), 409);
            $old = $locked->status;
            $locked->update(['status' => $to]);
            if ($to === DeviceStatus::Revoked) {
                $locked->credentials()->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($locked, $action, $request, ['from_status' => $old->value, 'to_status' => $to->value]);
        });

        return redirect()->route('admin.devices.show', $device)->with('status', $message);
    }

    private function audit(Device $device, string $action, Request $request, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'resource_type' => Device::class,
            'resource_id' => (string) $device->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
