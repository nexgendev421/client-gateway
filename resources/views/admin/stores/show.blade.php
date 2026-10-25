@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', $store->name)

@section('content')
    @php
        $statusClass = $store->status->value === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/10' : 'bg-amber-50 text-amber-800 ring-amber-600/10';
        $latest = $store->latestHealthCheck;
        $healthClass = match ($latest?->status?->value) { 'online' => 'text-emerald-700', 'offline' => 'text-rose-700', default => 'text-slate-500' };
        $storeDeviceCount = $store->licence_devices_count;
        $usagePercent = $store->max_devices > 0 ? min(100, (int) round(($storeDeviceCount / $store->max_devices) * 100)) : 0;
    @endphp
    <div class="mb-5"><a href="{{ route('admin.stores.index') }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← Stores</a></div>
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-start sm:p-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight">{{ $store->name }}</h1>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusClass }}">{{ str($store->status->value)->headline() }}</span>
                </div>
                <p class="mt-1.5 font-mono text-sm tracking-wide text-slate-500">{{ $store->store_code }}</p>
                <p class="mt-1 text-sm text-slate-500">Customer <a href="{{ route('admin.customers.show', $store->customer) }}" class="font-medium text-blue-700 hover:text-blue-900">{{ $store->customer->business_name }}</a></p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('update', $store)<a href="{{ route('admin.stores.edit', $store) }}" class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>@endcan
                @can('testConnection', $store)
                    <form method="POST" action="{{ route('admin.stores.test-connection', $store) }}">@csrf<button class="rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100">Test connection</button></form>
                @endcan
                @can('generateActivationCode', $store)
                    <a href="{{ route('admin.stores.activation-codes.create', $store) }}" class="rounded-lg bg-blue-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-800">Generate activation code</a>
                @endcan
                @can('changeStatus', $store)
                    @if ($store->status === \App\Enums\StoreStatus::Active)
                        <form method="POST" action="{{ route('admin.stores.suspend', $store) }}" onsubmit="return confirm('Suspend this store?')">@csrf<button class="rounded-lg border border-amber-300 px-3.5 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50">Suspend</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.stores.reactivate', $store) }}">@csrf<button class="rounded-lg border border-emerald-300 px-3.5 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50">Reactivate</button></form>
                    @endif
                @endcan
            </div>
        </div>
        @if (session('status'))<div role="status" class="mx-5 mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 sm:mx-6">{{ session('status') }}</div>@endif
        @if ($errors->has('licence_credential'))<div role="alert" class="mx-5 mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:mx-6">{{ $errors->first('licence_credential') }}</div>@endif
    </section>

    <nav aria-label="Store sections" class="mt-4 flex gap-1 border-b border-slate-200">
        <a href="{{ route('admin.stores.show', $store) }}" aria-current="page" class="border-b-2 border-blue-600 px-4 py-2.5 text-sm font-medium text-blue-800">Overview</a>
        <a href="{{ route('admin.stores.activation-codes.index', $store) }}" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-900">Activation Codes</a>
    </nav>

    <div class="mt-5 grid gap-4 xl:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
            <div class="flex items-center justify-between gap-3"><div><h2 class="text-sm font-semibold">POS endpoint</h2><p class="mt-1 text-xs text-slate-500">Registered customer server address</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">HTTPS endpoint</span></div>
            <a href="{{ $store->endpoint_url }}" rel="noreferrer" target="_blank" class="mt-4 block break-all rounded-lg bg-slate-50 px-4 py-3 font-mono text-sm text-blue-800 hover:underline">{{ $store->endpoint_url }}</a>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs text-slate-500">Endpoint status</dt><dd class="mt-1 text-sm font-semibold {{ $healthClass }}">{{ $latest ? str($latest->status->value)->headline() : 'Not checked' }}</dd>@if($latest)<p class="mt-0.5 text-[11px] text-slate-400">{{ $latest->checked_at->format('j M Y, H:i:s') }}</p>@endif</div>
                <div><dt class="text-xs text-slate-500">POS version</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $latest?->pos_version ?: 'Not reported' }}</dd></div>
                <div><dt class="text-xs text-slate-500">API version</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $latest?->api_version ?: 'Not reported' }}</dd></div>
            </dl>
            @if ($latest?->response_time_ms !== null)<p class="mt-3 text-xs text-slate-500">Response time: {{ $latest->response_time_ms }} ms</p>@endif
            @if ($latest?->safe_error_message)<p class="mt-4 rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ $latest->safe_error_message }}</p>@endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3"><div><h2 class="text-sm font-semibold">Device capacity</h2><p class="mt-1 text-xs text-slate-500">Registered devices at this store</p></div><a href="#devices" class="shrink-0 text-xs font-semibold text-blue-700 hover:text-blue-900">View devices</a></div>
            <p class="mt-5 text-3xl font-semibold tabular-nums tracking-tight">{{ number_format($storeDeviceCount) }}<span class="ml-1 text-base font-medium text-slate-400">/ {{ number_format($store->max_devices) }}</span></p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $storeDeviceCount >= $store->max_devices ? 'bg-amber-500' : 'bg-cyan-500' }}" style="width: {{ $usagePercent }}%"></div></div>
            <p class="mt-2 text-xs {{ $storeDeviceCount > $store->max_devices ? 'font-medium text-rose-700' : 'text-slate-500' }}">{{ $storeDeviceCount > $store->max_devices ? 'Over the store limit; existing devices remain registered.' : max(0, $store->max_devices - $storeDeviceCount).' store device slots available' }}</p>
            @if ($store->customer->licence?->max_devices !== null)
                <p class="mt-2 border-t border-slate-100 pt-2 text-xs {{ $limitUsage['devices_over_limit'] ? 'font-medium text-rose-700' : 'text-slate-500' }}">Customer licence devices: {{ $limitUsage['devices'] }} / {{ $limitUsage['device_limit'] }}{{ $limitUsage['devices_over_limit'] ? ' · Over limit; new device registrations are blocked.' : '' }}</p>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
            <h2 class="text-sm font-semibold">Customer and inherited licence</h2>
            <div class="mt-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div><a href="{{ route('admin.customers.show', $store->customer) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">{{ $store->customer->business_name }}</a><p class="mt-0.5 text-xs text-slate-500">{{ $store->customer->trading_name ?: 'Customer account' }}</p></div>
                @if ($store->customer->licence)
                    <div class="rounded-lg bg-slate-50 px-4 py-3 sm:min-w-48"><p class="text-[11px] uppercase tracking-wide text-slate-500">Customer licence</p><p class="mt-1 text-sm font-semibold capitalize text-slate-800">{{ str_replace('_', ' ', $store->customer->licence->status->value) }} · {{ $store->customer->licence->plan }}</p><p class="mt-0.5 text-xs text-slate-500">Valid until {{ $store->customer->licence->valid_until->format('j M Y') }}</p></div>
                @else
                    <p class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">No customer licence assigned</p>
                @endif
            </div>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Store information</h2>
            <dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">Store code</dt><dd class="font-mono font-medium">{{ $store->store_code }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd class="text-right">{{ $store->created_at->format('j M Y') }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Updated</dt><dd class="text-right">{{ $store->updated_at->format('j M Y') }}</dd></div></dl>
        </section>
    </div>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-sm font-semibold">POS server licence connection</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">Generate a one-time POS connection code. Enter it once in Settings → Licence on this store’s POS server. The POS will find this store and set up its licence automatically.</p>
                @php($serverCredential = $store->serverCredentials()->whereNull('revoked_at')->latest('id')->first())
                @if ($serverCredential)
                    <p class="mt-2 text-xs text-emerald-700">A POS connection code has been issued (ending {{ substr($serverCredential->token_prefix, -4) }}) · Last used {{ $serverCredential->last_used_at?->diffForHumans() ?? 'not yet' }}.</p>
                @else
                    <p class="mt-2 text-xs text-amber-700">No POS connection code has been issued yet.</p>
                @endif
            </div>
            @can('update', $store)
                <form method="POST" action="{{ route('admin.stores.licence-credential.issue', $store) }}" @if ($serverCredential) onsubmit="return confirm('Rotate this credential? The current POS server credential will stop working immediately.')" @endif>
                    @csrf
                    @if ($serverCredential)<input type="hidden" name="rotate" value="1">@endif
                    <button class="shrink-0 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100">{{ $serverCredential ? 'Create new connection code' : 'Generate POS connection code' }}</button>
                </form>
            @endcan
        </div>
    </section>

    <section id="devices" class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Registered devices</h2><p class="mt-1 text-xs text-slate-500">Showing up to 20 devices linked to this store.</p></header>
        @if ($devices->isEmpty())
            <p class="px-5 py-9 text-center text-sm text-slate-500">No devices are registered at this store.</p>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[620px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Device</th><th class="px-4 py-3">Platform</th><th class="px-4 py-3">Status</th><th class="px-5 py-3">Last seen</th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach ($devices as $device)<tr><td class="px-5 py-3 font-medium"><a href="{{ route('admin.devices.show', $device) }}" class="text-blue-700 hover:text-blue-900">{{ $device->name }}</a></td><td class="px-4 py-3 capitalize">{{ $device->platform->value }}</td><td class="px-4 py-3 capitalize">{{ $device->status->value }}</td><td class="px-5 py-3 text-xs text-slate-500">{{ $device->last_seen_at?->format('j M Y, H:i') ?? 'Never' }}</td></tr>@endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Recent activity</h2><p class="mt-1 text-xs text-slate-500">Latest store audit entries</p></header>
        @if ($activity->isEmpty())
            <p class="px-5 py-9 text-center text-sm text-slate-500">No activity has been recorded for this store.</p>
        @else
            <div class="divide-y divide-slate-100">@foreach ($activity as $event)<div class="flex flex-col justify-between gap-1 px-5 py-3 sm:flex-row sm:items-center"><div><p class="text-sm font-medium">{{ str($event->action)->replace(['.', '_'], ' ')->headline() }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $event->actor?->name ?? 'System' }}</p></div><time class="text-xs text-slate-400" datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('j M Y, H:i:s') }}</time></div>@endforeach</div>
        @endif
    </section>
    @if ($store->notes)<section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold">Notes</h2><p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $store->notes }}</p></section>@endif
@endsection
