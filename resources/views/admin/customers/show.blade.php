@extends('layouts.admin', ['activeNav' => 'customers'])

@section('title', $customer->business_name)

@section('content')
    @php
        $statusStyle = match ($customer->status->value) {
            'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/10',
            'suspended' => 'bg-amber-50 text-amber-800 ring-amber-600/10',
            default => 'bg-slate-100 text-slate-600 ring-slate-500/10',
        };
        $tabItems = [
            'overview' => 'Overview',
            'stores' => 'Stores',
            'devices' => 'Devices',
            'licence' => 'Licence',
            'activity' => 'Activity',
            'notes' => 'Notes',
        ];
    @endphp
    <div class="mb-5">
        <a href="{{ route('admin.customers.index') }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← Customers</a>
    </div>
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-start sm:p-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ $customer->business_name }}</h1>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusStyle }}">{{ str($customer->status->value)->headline() }}</span>
                </div>
                <p class="mt-1.5 text-sm text-slate-500">{{ $customer->trading_name ?: 'No trading name' }} · Customer #{{ $customer->id }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $customer)
                    <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                @endcan
                @can('changeStatus', $customer)
                    @if ($customer->status === \App\Enums\CustomerStatus::Active)
                        <form method="POST" action="{{ route('admin.customers.suspend', $customer) }}" onsubmit="return confirm('Suspend this customer?')">@csrf
                            <button class="rounded-lg border border-amber-300 px-3.5 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50">Suspend</button>
                        </form>
                    @elseif ($customer->status === \App\Enums\CustomerStatus::Suspended)
                        <form method="POST" action="{{ route('admin.customers.reactivate', $customer) }}">@csrf
                            <button class="rounded-lg border border-emerald-300 px-3.5 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50">Reactivate</button>
                        </form>
                    @endif
                @endcan
                @can('archive', $customer)
                    @if ($customer->status !== \App\Enums\CustomerStatus::Archived)
                        <form method="POST" action="{{ route('admin.customers.archive', $customer) }}" onsubmit="return confirm('Archive this customer? This account will remain in the directory but cannot be reactivated.')">@csrf
                            <button class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Archive</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
        @if (session('status'))
            <div role="status" class="mx-5 mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 sm:mx-6">{{ session('status') }}</div>
        @endif
        <nav aria-label="Customer sections" class="flex gap-1 overflow-x-auto border-t border-slate-200 px-3 sm:px-5">
            @foreach ($tabItems as $key => $label)
                <a href="{{ route('admin.customers.show', ['customer' => $customer, 'tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif class="shrink-0 border-b-2 px-3 py-3 text-sm font-medium {{ $tab === $key ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <div class="mt-5">
        @if ($tab === 'overview')
            <div class="grid gap-4 xl:grid-cols-3">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                    <h2 class="text-sm font-semibold">Customer details</h2>
                    <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Business name</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $customer->business_name }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Trading name</dt><dd class="mt-1 text-sm text-slate-800">{{ $customer->trading_name ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Contact name</dt><dd class="mt-1 text-sm text-slate-800">{{ $customer->contact_name ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Email</dt><dd class="mt-1 break-all text-sm text-slate-800">{{ $customer->email ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Phone</dt><dd class="mt-1 text-sm text-slate-800">{{ $customer->phone ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Created</dt><dd class="mt-1 text-sm text-slate-800">{{ $customer->created_at->format('j F Y, H:i') }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold">Account summary</h2>
                    <dl class="mt-4 space-y-3">
                        <div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-sm text-slate-500">Stores</dt><dd class="text-sm font-semibold tabular-nums">{{ number_format($customer->stores_count) }}</dd></div>
                        <div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-sm text-slate-500">Devices</dt><dd class="text-sm font-semibold tabular-nums">{{ number_format($customer->devices_count) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-sm text-slate-500">Licence</dt><dd class="text-sm font-medium capitalize">{{ $customer->licence ? str_replace('_', ' ', $customer->licence->status->value) : 'None' }}</dd></div>
                    </dl>
                </section>
            </div>
        @elseif ($tab === 'stores')
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Stores</h2><p class="mt-1 text-xs text-slate-500">{{ number_format($customer->stores_count) }} associated stores</p></header>
                @if ($stores->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-slate-500">No stores are registered for this customer.</p>
                @else
                    <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Store</th><th class="px-4 py-3">Store code</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Devices</th><th class="px-5 py-3">Latest health</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach ($stores as $store)
                            <tr><td class="px-5 py-3 font-medium">{{ $store->name }}</td><td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $store->store_code }}</td><td class="px-4 py-3 capitalize">{{ $store->status->value }}</td><td class="px-4 py-3 tabular-nums">{{ $store->devices_count }}</td><td class="px-5 py-3 text-xs text-slate-500">{{ $store->latestHealthCheck?->checked_at?->format('j M Y, H:i') ?? 'No checks' }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                    @if ($stores->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $stores->links() }}</div>@endif
                @endif
            </section>
        @elseif ($tab === 'devices')
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Devices</h2><p class="mt-1 text-xs text-slate-500">{{ number_format($customer->devices_count) }} registered devices across customer stores</p></header>
                @if ($devices->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-slate-500">No devices are registered for this customer.</p>
                @else
                    <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Device</th><th class="px-4 py-3">Store</th><th class="px-4 py-3">Platform</th><th class="px-4 py-3">Status</th><th class="px-5 py-3">Last seen</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach ($devices as $device)
                            <tr><td class="px-5 py-3 font-medium"><a href="{{ route('admin.devices.show', $device) }}" class="text-blue-700 hover:text-blue-900">{{ $device->name }}</a></td><td class="px-4 py-3 text-slate-600">{{ $device->store->name }}</td><td class="px-4 py-3 capitalize">{{ $device->platform->value }}</td><td class="px-4 py-3 capitalize">{{ $device->status->value }}</td><td class="px-5 py-3 text-xs text-slate-500">{{ $device->last_seen_at?->format('j M Y, H:i') ?? 'Never' }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                    @if ($devices->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $devices->links() }}</div>@endif
                @endif
            </section>
        @elseif ($tab === 'licence')
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Customer licence</h2>
                @if ($customer->licence)
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-xs text-slate-500">Plan</dt><dd class="mt-1 text-sm font-medium">{{ $customer->licence->plan }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-1 text-sm font-medium capitalize">{{ str_replace('_', ' ', $customer->licence->status->value) }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Starts</dt><dd class="mt-1 text-sm">{{ $customer->licence->starts_at->format('j M Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Valid until</dt><dd class="mt-1 text-sm">{{ $customer->licence->valid_until->format('j M Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Maximum stores</dt><dd class="mt-1 text-sm">{{ $customer->licence->max_stores ?? 'No limit set' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Maximum devices</dt><dd class="mt-1 text-sm">{{ $customer->licence->max_devices ?? 'No limit set' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No licence is associated with this customer.</p>
                @endif
            </section>
        @elseif ($tab === 'activity')
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Customer activity</h2><p class="mt-1 text-xs text-slate-500">Latest audit entries for this account</p></header>
                @if ($activity->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-slate-500">No activity has been recorded for this customer.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($activity as $event)
                            <div class="flex flex-col justify-between gap-1 px-5 py-3 sm:flex-row sm:items-center"><div><p class="text-sm font-medium text-slate-800">{{ str($event->action)->replace(['.', '_'], ' ')->headline() }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $event->actor?->name ?? 'System' }}</p></div><time class="text-xs text-slate-400" datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('j M Y, H:i:s') }}</time></div>
                        @endforeach
                    </div>
                @endif
            </section>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Internal notes</h2>
                @if ($customer->notes)
                    <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $customer->notes }}</p>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No internal notes have been added.</p>
                @endif
                @can('update', $customer)<a href="{{ route('admin.customers.edit', $customer) }}" class="mt-5 inline-flex text-sm font-medium text-blue-700 hover:text-blue-900">Edit notes</a>@endcan
            </section>
        @endif
    </div>
@endsection
