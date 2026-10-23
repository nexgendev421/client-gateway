@extends('layouts.admin', ['activeNav' => 'activation-codes'])

@section('title', 'Activation Codes')

@section('content')
    <div class="mb-5"><p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Router administration</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Activation Codes</h1><p class="mt-1 text-sm text-slate-500">Track issued codes, their usage, and expiry. Full codes are never available here.</p></div>
    @if (session('status'))<div role="status" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('status') }}</div>@endif
    <form method="GET" class="mb-4 flex flex-col gap-2 sm:flex-row">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search store, customer, or suffix" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">All statuses</option>@foreach(['active' => 'Active', 'used' => 'Used', 'expired' => 'Expired', 'revoked' => 'Revoked'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
        <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Search</button>
    </form>
    @include('admin.activation-codes.table', ['codes' => $codes])
@endsection
