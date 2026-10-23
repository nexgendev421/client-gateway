@extends('layouts.admin', ['activeNav' => 'licences'])

@section('title', 'Edit licence')

@section('content')
    <div class="mb-5"><a href="{{ route('admin.licences.show', $licence) }}" class="text-xs font-medium text-blue-700">← {{ $licence->customer->business_name }}</a></div>
    <section class="mx-auto max-w-4xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h1 class="text-xl font-semibold">Edit licence</h1><p class="mt-1 text-sm text-slate-500">Update plan, dates, limits, feature entitlements, or internal notes.</p>
        @include('admin.licences.form', ['action' => route('admin.licences.update', $licence), 'method' => 'PUT', 'licence' => $licence, 'customers' => collect(), 'statuses' => [], 'selectedCustomerId' => null])
    </section>
@endsection
