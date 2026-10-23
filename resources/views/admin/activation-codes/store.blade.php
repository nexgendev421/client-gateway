@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'Activation Codes · '.$store->name)

@section('content')
    <div class="mb-5"><a href="{{ route('admin.stores.show', $store) }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← {{ $store->name }}</a></div>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">{{ $store->store_code }}</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $store->name }} · Activation Codes</h1><p class="mt-1 text-sm text-slate-500">Issued codes show only their masked suffix.</p></div>
        @can('generateActivationCode', $store)<a href="{{ route('admin.stores.activation-codes.create', $store) }}" class="inline-flex justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Generate code</a>@endcan
    </div>
    <nav aria-label="Store sections" class="mt-5 flex gap-1 border-b border-slate-200"><a href="{{ route('admin.stores.show', $store) }}" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-900">Overview</a><a href="{{ route('admin.stores.activation-codes.index', $store) }}" aria-current="page" class="border-b-2 border-blue-600 px-4 py-2.5 text-sm font-medium text-blue-800">Activation Codes</a></nav>
    @if (session('status'))<div role="status" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('status') }}</div>@endif
    @include('admin.activation-codes.table', ['codes' => $codes])
@endsection
