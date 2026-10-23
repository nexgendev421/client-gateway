@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'Edit '.$store->name)

@section('content')
    <div class="mb-6"><a href="{{ route('admin.stores.show', $store) }}" class="text-xs font-medium text-blue-700">← {{ $store->name }}</a><h1 class="mt-2 text-2xl font-semibold tracking-tight">Edit store</h1><p class="mt-1 text-sm text-slate-500">Update the customer, endpoint, device limit, or store status.</p></div>
    @include('admin.stores.form')
@endsection
