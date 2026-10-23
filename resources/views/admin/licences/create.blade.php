@extends('layouts.admin', ['activeNav' => 'licences'])

@section('title', 'Create licence')

@section('content')
    <div class="mb-5"><a href="{{ route('admin.licences.index') }}" class="text-xs font-medium text-blue-700">← Licences</a></div>
    <section class="mx-auto max-w-4xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h1 class="text-xl font-semibold">Create customer licence</h1><p class="mt-1 text-sm text-slate-500">One licence applies across all stores belonging to the customer.</p>
        @if($customers->isEmpty())
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Every active customer already has a licence. A customer can have only one current licence record.</p>
        @else
            @include('admin.licences.form', ['action' => route('admin.licences.store'), 'method' => 'POST', 'licence' => null, 'customers' => $customers, 'statuses' => $statuses, 'selectedCustomerId' => $selectedCustomerId])
        @endif
    </section>
@endsection
