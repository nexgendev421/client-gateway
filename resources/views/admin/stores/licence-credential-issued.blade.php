@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'POS connection code')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.stores.show', $store) }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← {{ $store->name }}</a>
        <section class="mt-4 rounded-xl border border-amber-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold">Copy this POS connection code</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">This code is shown only once. In the store’s POS, open Settings → Licence and enter it in the POS connection code box. The POS will find this store and connect its licence automatically. Do not put this code in the Windows or Android till app.</p>
            <label class="mt-5 block text-xs font-semibold text-slate-600" for="store-server-token">POS connection code</label>
            <div class="mt-2 flex gap-2">
                <input id="store-server-token" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm" value="{{ $token }}" readonly autocomplete="off" spellcheck="false">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('store-server-token').value).then(()=>this.textContent='Copied')" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Copy</button>
            </div>
            <p class="mt-4 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">If this credential is lost, rotate it from the Store page. Rotation immediately revokes this one.</p>
            <a href="{{ route('admin.stores.show', $store) }}" class="mt-5 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Continue to store</a>
        </section>
    </div>
@endsection
