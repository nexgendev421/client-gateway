@php
    $editing = isset($store);
    $inputClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100';
@endphp
@if ($errors->any())
    <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-medium">Please correct the following:</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ $editing ? route('admin.stores.update', $store) : route('admin.stores.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5"><h2 class="text-sm font-semibold">Store details</h2><p class="mt-1 text-xs text-slate-500">Register the customer location and its POS server endpoint.</p></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="customer_id" class="text-xs font-semibold text-slate-700">Customer <span class="text-rose-600">*</span></label>
                <select id="customer_id" name="customer_id" required class="{{ $inputClass }}">
                    <option value="">Select a customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id', $store->customer_id ?? '') === (string) $customer->id)>{{ $customer->business_name }}@if($customer->trading_name) · {{ $customer->trading_name }}@endif</option>
                    @endforeach
                </select>
                @error('customer_id')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="name" class="text-xs font-semibold text-slate-700">Store name <span class="text-rose-600">*</span></label>
                <input id="name" name="name" value="{{ old('name', $store->name ?? '') }}" required maxlength="255" class="{{ $inputClass }}">
                @error('name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="store_code" class="text-xs font-semibold text-slate-700">Store code <span class="text-rose-600">*</span></label>
                <input id="store_code" name="store_code" value="{{ old('store_code', $store->store_code ?? '') }}" required minlength="3" maxlength="20" pattern="[A-Za-z0-9]{3,20}" autocomplete="off" class="{{ $inputClass }} uppercase">
                <p class="mt-1 text-[11px] text-slate-500">3–20 letters or numbers. Stored and shown in uppercase, for example ASIAHUD.</p>
                @error('store_code')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="endpoint_url" class="text-xs font-semibold text-slate-700">POS endpoint URL <span class="text-rose-600">*</span></label>
                <input id="endpoint_url" name="endpoint_url" type="url" value="{{ old('endpoint_url', $store->endpoint_url ?? '') }}" required maxlength="2048" placeholder="https://pos.example.co.uk" class="{{ $inputClass }}">
                <p class="mt-1 text-[11px] text-slate-500">Use a public hostname. Production endpoints must use HTTPS on port 443. Credentials and query strings are not allowed.</p>
                @error('endpoint_url')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="max_devices" class="text-xs font-semibold text-slate-700">Maximum devices <span class="text-rose-600">*</span></label>
                <input id="max_devices" name="max_devices" type="number" min="1" max="65535" value="{{ old('max_devices', $store->max_devices ?? 10) }}" required class="{{ $inputClass }}">
                @error('max_devices')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="status" class="text-xs font-semibold text-slate-700">Status</label>
                <select id="status" name="status" class="{{ $inputClass }}">
                    @foreach (['active' => 'Active', 'suspended' => 'Suspended'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $store->status->value ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="text-xs font-semibold text-slate-700">Notes</label>
                <textarea id="notes" name="notes" rows="4" maxlength="10000" class="{{ $inputClass }}">{{ old('notes', $store->notes ?? '') }}</textarea>
                @error('notes')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
    <div class="flex flex-col-reverse justify-between gap-3 sm:flex-row sm:items-center">
        <a href="{{ $editing ? route('admin.stores.show', $store) : route('admin.stores.index') }}" class="rounded-lg px-4 py-2.5 text-center text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
        <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">{{ $editing ? 'Save changes' : 'Create store' }}</button>
    </div>
</form>
