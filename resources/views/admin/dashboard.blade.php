@extends('layouts.admin', ['activeNav' => 'dashboard'])

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Overview</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">A live view of customers, stores, devices, and licences.</p>
        </div>
        <p class="text-xs text-slate-500">Updated {{ now()->format('j M Y, H:i') }}</p>
    </div>

    <section aria-label="Router metrics" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-8">
        @foreach ($metrics as $metric)
            @php
                $accent = match ($metric['tone']) {
                    'cyan' => 'bg-cyan-500',
                    'amber' => 'bg-amber-500',
                    'rose' => 'bg-rose-500',
                    'slate' => 'bg-slate-400',
                    default => 'bg-blue-600',
                };
            @endphp
            <article class="relative min-h-[112px] overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="absolute inset-y-0 left-0 w-1 {{ $accent }}"></span>
                <p class="text-xs font-medium leading-5 text-slate-500">{{ $metric['label'] }}</p>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ number_format($metric['value']) }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-4 xl:grid-cols-2">
        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Devices needing an update</h2><p class="mt-0.5 text-xs text-slate-500">Active devices below the latest published version</p></div><div class="flex items-center gap-3"><span class="rounded-md {{ $outdatedDeviceCount === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }} px-2 py-1 text-[11px] font-medium">{{ number_format($outdatedDeviceCount) }} outdated</span><a href="{{ route('admin.settings.app-versions') }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Manage versions</a></div></header>
            @forelse ($outdatedDevices as $device)
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0"><div class="min-w-0"><a href="{{ route('admin.devices.show', $device) }}" class="truncate text-sm font-medium text-blue-700 hover:text-blue-900">{{ $device->name }}</a><p class="mt-0.5 truncate text-xs text-slate-500">{{ $device->store->customer->business_name }} · {{ $device->store->name }}</p></div><div class="shrink-0 text-right"><p class="text-xs font-medium capitalize text-slate-600">{{ $device->platform->value }}</p><p class="mt-0.5 text-[11px] text-slate-500">{{ $device->app_version ?: 'Version not reported' }} → {{ $latestAppVersions[$device->platform->value] }}</p></div></div>
            @empty
                <div class="px-5 py-7 text-center"><p class="text-sm font-medium text-slate-700">No outdated active devices</p><p class="mt-1 text-xs text-slate-500">Publish a latest version in Settings to monitor updates.</p></div>
            @endforelse
        </article>

        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 class="text-sm font-semibold text-slate-900">Recent device activations</h2><p class="mt-0.5 text-xs text-slate-500">Most recently registered devices</p></div>
                <span class="rounded-md bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700">{{ $recentActivations->count() }} shown</span>
            </header>
            @forelse ($recentActivations as $device)
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $device->name }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ $device->store->customer->business_name }} · {{ $device->store->name }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs font-medium capitalize text-slate-600">{{ $device->platform->value }}</p>
                        <time class="mt-0.5 block text-[11px] text-slate-400" datetime="{{ $device->registered_at->toIso8601String() }}">{{ $device->registered_at->format('j M, H:i') }}</time>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">No device activations yet</p>
                    <p class="mt-1 text-xs text-slate-500">Newly registered devices will appear here.</p>
                </div>
            @endforelse
        </article>

        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 class="text-sm font-semibold text-slate-900">Recent audit events</h2><p class="mt-0.5 text-xs text-slate-500">Latest recorded Router activity</p></div>
                <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">{{ $recentAuditEvents->count() }} shown</span>
            </header>
            @forelse ($recentAuditEvents as $event)
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ str($event->action)->replace(['.', '_'], ' ')->headline() }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ $event->actor?->name ?? 'System' }}@if($event->resource_type) · {{ $event->resource_type }} {{ $event->resource_id }}@endif</p>
                    </div>
                    <time class="shrink-0 text-xs text-slate-400" datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('j M, H:i') }}</time>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">No audit events recorded</p>
                    <p class="mt-1 text-xs text-slate-500">Administrative events will be listed here as they are recorded.</p>
                </div>
            @endforelse
        </article>

        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 class="text-sm font-semibold text-slate-900">Stores reporting offline</h2><p class="mt-0.5 text-xs text-slate-500">Stores whose latest endpoint check is offline</p></div>
                <span class="rounded-md {{ $offlineStores->isEmpty() ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} px-2 py-1 text-[11px] font-medium">{{ $offlineStores->count() }} offline</span>
            </header>
            @forelse ($offlineStores as $store)
                <div class="grid grid-cols-1 gap-2 border-b border-slate-100 px-5 py-3 last:border-0 sm:grid-cols-[1fr_1fr_auto] sm:items-center sm:gap-4">
                    <div><p class="text-sm font-medium text-slate-800">{{ $store->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $store->customer->business_name }}</p></div>
                    <div class="truncate text-xs text-slate-500">{{ $store->endpoint_url }}</div>
                    <time class="text-xs text-slate-400 sm:text-right" datetime="{{ $store->latestHealthCheck->checked_at->toIso8601String() }}">Last check {{ $store->latestHealthCheck->checked_at->format('j M Y, H:i') }}</time>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">No stores are reporting offline</p>
                    <p class="mt-1 text-xs text-slate-500">Stores with an offline latest health check will appear here.</p>
                </div>
            @endforelse
        </article>
    </section>
@endsection
