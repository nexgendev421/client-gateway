@extends('layouts.admin', ['activeNav' => 'system-health'])

@section('title', 'System Health')

@section('content')
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Router administration</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">System Health</h1>
        <p class="mt-1 text-sm text-slate-500">Latest manually requested health result for each customer POS endpoint.</p>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        @foreach ([['Online', $onlineCount, 'text-emerald-700'], ['Offline', $offlineCount, 'text-rose-700'], ['Not checked', $uncheckedCount, 'text-slate-600']] as [$label, $count, $color])
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold {{ $color }}">{{ number_format($count) }}</p></section>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Store endpoints</h2><p class="mt-1 text-xs text-slate-500">Run a check from the store detail page. Automated checks are not enabled.</p></header>
        @if ($stores->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-slate-500">No stores have been registered.</p>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Store</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Response</th><th class="px-4 py-3">Last checked</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($stores as $store)
                        @php($health = $store->latestHealthCheck)
                        @php($isOnline = $health?->status === \App\Enums\HealthCheckStatus::Online)
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('admin.stores.show', $store) }}" class="font-medium text-blue-700 hover:text-blue-900">{{ $store->name }}</a><p class="font-mono text-xs text-slate-500">{{ $store->store_code }}</p></td>
                            <td class="px-4 py-3 text-slate-600">{{ $store->customer->business_name }}</td>
                            <td class="px-4 py-3">@if($health)<span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $isOnline ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span>@else<span class="text-xs text-slate-400">Not checked</span>@endif</td>
                            <td class="px-4 py-3 text-slate-600">{{ $health?->response_time_ms !== null ? $health->response_time_ms.' ms' : '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $health?->checked_at?->format('j M Y, H:i:s') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('admin.stores.show', $store) }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">View store</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $stores->links() }}</div>
        @endif
    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Recent checks</h2></header>
        @if ($recentChecks->isEmpty())
            <p class="px-5 py-9 text-center text-sm text-slate-500">No endpoint checks have been run.</p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($recentChecks as $check)
                    <div class="flex flex-col justify-between gap-2 px-5 py-3 sm:flex-row sm:items-center">
                        <div><a href="{{ route('admin.stores.show', $check->store) }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">{{ $check->store->name }}</a><span class="ml-2 rounded-full px-2 py-0.5 text-[11px] {{ $check->status === \App\Enums\HealthCheckStatus::Online ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $check->status === \App\Enums\HealthCheckStatus::Online ? 'Online' : 'Offline' }}</span>
                            @if($check->safe_error_message)<p class="mt-1 text-xs text-slate-500">{{ $check->safe_error_message }}</p>@elseif($check->pos_version || $check->api_version)<p class="mt-1 text-xs text-slate-500">POS {{ $check->pos_version ?? '—' }} · API {{ $check->api_version ?? '—' }}</p>@endif
                        </div>
                        <div class="text-xs text-slate-500">{{ $check->response_time_ms !== null ? $check->response_time_ms.' ms · ' : '' }}{{ $check->checked_at->format('j M Y, H:i:s') }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
