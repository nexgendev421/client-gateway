@extends('layouts.admin', ['activeNav' => 'customers'])

@section('title', 'Create customer')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.customers.index') }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← Customers</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Create customer</h1>
        <p class="mt-1 text-sm text-slate-500">Add a customer account to the Router directory.</p>
    </div>
    @include('admin.customers.form')
@endsection
