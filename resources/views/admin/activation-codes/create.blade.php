@extends('layouts.admin', ['activeNav' => 'stores'])

@section('title', 'Generate Activation Code')

@section('content')
    <div class="mb-5"><a href="{{ route('admin.stores.activation-codes.index', $store) }}" class="text-xs font-medium text-blue-700 hover:text-blue-900">← Store activation codes</a></div>
    <section class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-5 py-4 sm:px-6"><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">{{ $store->name }} · {{ $store->store_code }}</p><h1 class="mt-1 text-xl font-semibold">Generate activation code</h1><p class="mt-1 text-sm text-slate-500">The complete code appears once after generation.</p></header>
        <form method="POST" action="{{ route('admin.stores.activation-codes.store', $store) }}" class="space-y-5 p-5 sm:p-6">
            @csrf
            <div>
                <label for="expires_in_hours" class="mb-1.5 block text-sm font-medium text-slate-700">Expires after</label>
                <select id="expires_in_hours" name="expires_in_hours" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <option value="1" @selected(old('expires_in_hours', '24') == '1')>1 hour</option>
                    <option value="24" @selected(old('expires_in_hours', '24') == '24')>24 hours</option>
                    <option value="168" @selected(old('expires_in_hours') == '168')>7 days</option>
                </select>
                @error('expires_in_hours')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="max_uses" class="mb-1.5 block text-sm font-medium text-slate-700">Maximum uses</label>
                <input id="max_uses" name="max_uses" type="number" min="1" max="100" value="{{ old('max_uses', 1) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <p class="mt-1 text-xs text-slate-500">Enter a value from 1 to 100.</p>
                @error('max_uses')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="intended_device_name" class="mb-1.5 block text-sm font-medium text-slate-700">Intended device name <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="intended_device_name" name="intended_device_name" type="text" maxlength="255" value="{{ old('intended_device_name') }}" placeholder="For example: Front counter 1" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <p class="mt-1 text-xs text-slate-500">If set, redemption must include this device name.</p>
                @error('intended_device_name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4"><a href="{{ route('admin.stores.activation-codes.index', $store) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Generate code</button></div>
        </form>
    </section>
@endsection
