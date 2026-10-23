@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'Stores')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Directory</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Stores</h1><p class="mt-1 text-sm text-slate-500">POS endpoints and device capacity for each customer location.</p></div>
        @can('create', App\Models\Store::class)<a href="{{ route('admin.stores.create') }}" class="inline-flex justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Add store</a>@endcan
    </div>
    @if (session('status'))<div role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('admin.stores.index') }}" class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row">
            <label for="store-search" class="sr-only">Search stores</label>
            <input id="store-search" name="q" value="{{ $search }}" type="search" placeholder="Search store, code or customer" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
            <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Search</button>
            @if ($search !== '')<a href="{{ route('admin.stores.index') }}" class="rounded-lg px-3 py-2.5 text-center text-sm text-slate-500">Clear</a>@endif
        </form>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Store</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Endpoint</th><th class="px-4 py-3">Devices</th><th class="px-4 py-3">Health</th><th class="px-4 py-3">Status</th><th class="px-5 py-3">Created</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stores as $store)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5"><a href="{{ route('admin.stores.show', $store) }}" class="font-medium text-slate-900 hover:text-blue-700">{{ $store->name }}</a><p class="mt-0.5 font-mono text-xs text-slate-500">{{ $store->store_code }}</p></td>
                        <td class="px-4 py-3.5 text-slate-700">{{ $store->customer->business_name }}</td>
                        <td class="max-w-[260px] truncate px-4 py-3.5 text-xs text-slate-500" title="{{ $store->endpoint_url }}">{{ $store->endpoint_url }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 tabular-nums text-slate-700">{{ $store->devices_count }} / {{ $store->max_devices }}</td>
                        <td class="px-4 py-3.5">
                            @if ($store->latestHealthCheck)
                                @php($healthClass = match($store->latestHealthCheck->status->value) { 'online' => 'text-emerald-700', default => 'text-rose-700' })
                                <span class="text-xs font-medium {{ $healthClass }}">{{ str($store->latestHealthCheck->status->value)->headline() }}</span>
                            @else<span class="text-xs text-slate-400">Not checked</span>@endif
                        </td>
                        <td class="px-4 py-3.5"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $store->status->value === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ str($store->status->value)->headline() }}</span></td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-xs text-slate-500">{{ $store->created_at->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-700">{{ $search !== '' ? 'No stores match your search' : 'No stores yet' }}</p><p class="mt-1 text-xs text-slate-500">{{ $search !== '' ? 'Try another store, code, or customer.' : 'Stores added to the Router will appear here.' }}</p></td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($stores->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $stores->links() }}</div>@endif
    </section>
@endsection
