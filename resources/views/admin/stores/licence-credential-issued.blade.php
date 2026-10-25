@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'POS server credential')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.stores.show', $store) }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← {{ $store->name }}</a>
        <section class="mt-4 rounded-xl border border-amber-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold">Save this POS server credential</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">This credential is shown only once. Copy it into the <code>.env</code> file on this store’s POS VPS. It gives that POS server access only to its signed licence refresh endpoint. Do not send it to a cashier or put it in the Windows/Android app.</p>
            <label class="mt-5 block text-xs font-semibold text-slate-600" for="store-server-token">POS server credential</label>
            <div class="mt-2 flex gap-2">
                <input id="store-server-token" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm" value="{{ $token }}" readonly autocomplete="off" spellcheck="false">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('store-server-token').value).then(()=>this.textContent='Copied')" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Copy</button>
            </div>
            <p class="mt-4 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">If this credential is lost, rotate it from the Store page. Rotation immediately revokes this one.</p>
            <a href="{{ route('admin.stores.show', $store) }}" class="mt-5 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Continue to store</a>
        </section>
    </div>
@endsection
