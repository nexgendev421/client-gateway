@extends('layouts.admin', ['activeNav' => 'customers'])

@section('title', 'Customers')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Directory</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Customers</h1>
            <p class="mt-1 text-sm text-slate-500">Manage customer accounts and review their Router footprint.</p>
        </div>
        @can('create', App\Models\Customer::class)
            <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-100">Add customer</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row">
            <label for="customer-search" class="sr-only">Search customers</label>
            <input id="customer-search" name="q" value="{{ $search }}" type="search" placeholder="Search business, trading name, contact or email" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Search</button>
            @if ($search !== '')<a href="{{ route('admin.customers.index') }}" class="rounded-lg px-3 py-2.5 text-center text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>@endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Business name</th>
                        <th class="px-4 py-3">Trading name</th>
                        <th class="px-4 py-3 text-right">Stores</th>
                        <th class="px-4 py-3 text-right">Devices</th>
                        <th class="px-4 py-3">Licence</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-slate-900 hover:text-blue-700">{{ $customer->business_name }}</a>
                                @if ($customer->email)<p class="mt-0.5 text-xs text-slate-500">{{ $customer->email }}</p>@endif
                            </td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $customer->trading_name ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($customer->stores_count) }}</td>
                            <td class="px-4 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($customer->devices_count) }}</td>
                            <td class="px-4 py-3.5">
                                @if ($customer->licence)
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $customer->licence->status->value) }}</span>
                                @else
                                    <span class="text-slate-400">No licence</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @php($statusClass = match($customer->status->value) { 'active' => 'bg-emerald-50 text-emerald-700', 'suspended' => 'bg-amber-50 text-amber-800', default => 'bg-slate-100 text-slate-600' })
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">{{ str($customer->status->value)->headline() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-500">{{ $customer->created_at->format('j M Y') }}</td>
                            <td class="px-5 py-3.5 text-right"><a href="{{ route('admin.customers.show', $customer) }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center">
                            <p class="text-sm font-medium text-slate-700">{{ $search !== '' ? 'No customers match your search' : 'No customers yet' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $search !== '' ? 'Try another business name, contact, or email.' : 'Create a customer to begin managing Router stores and devices.' }}</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="border-t border-slate-200 px-5 py-3">{{ $customers->links() }}</div>
        @endif
    </section>
@endsection
