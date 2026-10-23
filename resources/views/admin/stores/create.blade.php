@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'Create store')

@section('content')
    <div class="mb-6"><a href="{{ route('admin.stores.index') }}" class="text-xs font-medium text-blue-700">← Stores</a><h1 class="mt-2 text-2xl font-semibold tracking-tight">Create store</h1><p class="mt-1 text-sm text-slate-500">Add a POS endpoint under a customer account.</p></div>
    @include('admin.stores.form')
@endsection
