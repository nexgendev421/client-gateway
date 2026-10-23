@extends('layouts.admin', ['activeNav' => 'customers'])

@section('title', 'Edit '.$customer->business_name)

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.customers.show', $customer) }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← {{ $customer->business_name }}</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Edit customer</h1>
        <p class="mt-1 text-sm text-slate-500">Update account details and status.</p>
    </div>
    @include('admin.customers.form')
@endsection
