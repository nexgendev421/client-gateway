@php
    $editing = isset($customer);
    $archived = $editing && $customer->status === \App\Enums\CustomerStatus::Archived;
    $inputClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100';
@endphp

@if ($errors->any())
    <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-medium">Please correct the following:</p>
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ $editing ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5"><h2 class="text-sm font-semibold text-slate-900">Business details</h2><p class="mt-1 text-xs text-slate-500">The core contact information for this customer account.</p></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="business_name" class="text-xs font-semibold text-slate-700">Business name <span class="text-rose-600">*</span></label>
                <input id="business_name" name="business_name" value="{{ old('business_name', $customer->business_name ?? '') }}" required maxlength="255" class="{{ $inputClass }}" autocomplete="organization">
                @error('business_name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="trading_name" class="text-xs font-semibold text-slate-700">Trading name</label>
                <input id="trading_name" name="trading_name" value="{{ old('trading_name', $customer->trading_name ?? '') }}" maxlength="255" class="{{ $inputClass }}">
                @error('trading_name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="contact_name" class="text-xs font-semibold text-slate-700">Contact name</label>
                <input id="contact_name" name="contact_name" value="{{ old('contact_name', $customer->contact_name ?? '') }}" maxlength="255" class="{{ $inputClass }}" autocomplete="name">
                @error('contact_name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="text-xs font-semibold text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $customer->email ?? '') }}" maxlength="255" class="{{ $inputClass }}" autocomplete="email">
                @error('email')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="text-xs font-semibold text-slate-700">Phone</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone', $customer->phone ?? '') }}" maxlength="50" class="{{ $inputClass }}" autocomplete="tel">
                @error('phone')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="status" class="text-xs font-semibold text-slate-700">Status</label>
                @if ($archived)
                    <p class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600">Archived. Archived accounts cannot be reactivated.</p>
                @else
                    <select id="status" name="status" class="{{ $inputClass }}">
                        @foreach (['active' => 'Active', 'suspended' => 'Suspended'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $customer->status->value ?? 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                @endif
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="text-xs font-semibold text-slate-700">Notes</label>
                <textarea id="notes" name="notes" rows="5" maxlength="10000" class="{{ $inputClass }}">{{ old('notes', $customer->notes ?? '') }}</textarea>
                @error('notes')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
    <div class="flex flex-col-reverse justify-between gap-3 sm:flex-row sm:items-center">
        <a href="{{ $editing ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="rounded-lg px-4 py-2.5 text-center text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
        <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-100">{{ $editing ? 'Save changes' : 'Create customer' }}</button>
    </div>
</form>
