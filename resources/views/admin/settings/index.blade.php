@extends('layouts.admin')

@section('title', 'Router Settings')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div><p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Administration</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Router settings</h1><p class="mt-2 text-sm text-slate-500">Configure safe defaults for activations, health monitoring, licensing and device communication.</p></div>
    @if(session('status'))<div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
        @csrf @method('PUT')
        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-base font-semibold">Activation defaults</h2><p class="mt-1 text-sm text-slate-500">These values prefill new activation codes. Staff can adjust them when generating a code.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label for="default_activation_expiry_hours" class="mb-1 block text-sm font-medium">Default code expiry</label><select id="default_activation_expiry_hours" name="default_activation_expiry_hours" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@foreach([1 => '1 hour', 24 => '24 hours', 168 => '7 days'] as $value => $label)<option value="{{ $value }}" @selected((string)old('default_activation_expiry_hours', $settings['default_activation_expiry_hours']) === (string)$value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="default_activation_max_uses" class="mb-1 block text-sm font-medium">Default maximum uses</label><input id="default_activation_max_uses" name="default_activation_max_uses" type="number" min="1" max="100" required value="{{ old('default_activation_max_uses', $settings['default_activation_max_uses']) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-base font-semibold">Monitoring and device communication</h2><p class="mt-1 text-sm text-slate-500">The scheduled health-check runner checks every minute and applies the selected interval. Devices are considered offline after three expected heartbeat intervals.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><label for="health_check_frequency_minutes" class="mb-1 block text-sm font-medium">POS endpoint check interval</label><select id="health_check_frequency_minutes" name="health_check_frequency_minutes" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@foreach([5, 15, 30, 60] as $value)<option value="{{ $value }}" @selected((string)old('health_check_frequency_minutes', $settings['health_check_frequency_minutes']) === (string)$value)>{{ $value }} minutes</option>@endforeach</select></div>
                <div><label for="health_history_retention_days" class="mb-1 block text-sm font-medium">Health history retention (days)</label><input id="health_history_retention_days" name="health_history_retention_days" type="number" min="1" max="3650" required value="{{ old('health_history_retention_days', $settings['health_history_retention_days']) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <div><label for="heartbeat_expected_interval_minutes" class="mb-1 block text-sm font-medium">Expected device heartbeat (minutes)</label><input id="heartbeat_expected_interval_minutes" name="heartbeat_expected_interval_minutes" type="number" min="1" max="1440" required value="{{ old('heartbeat_expected_interval_minutes', $settings['heartbeat_expected_interval_minutes']) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-base font-semibold">Licence defaults</h2>
            <div class="mt-4 max-w-sm"><label for="default_licence_grace_days" class="mb-1 block text-sm font-medium">Default grace period</label><select id="default_licence_grace_days" name="default_licence_grace_days" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@foreach([1, 3, 7, 14, 30, 60] as $value)<option value="{{ $value }}" @selected((string)old('default_licence_grace_days', $settings['default_licence_grace_days']) === (string)$value)>{{ $value }} days</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Used when a licence is moved into Grace without a custom end date.</p></div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-base font-semibold">Support contact and maintenance notice</h2><p class="mt-1 text-sm text-slate-500">Contact details and the notice are included in device configuration responses.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label for="support_email" class="mb-1 block text-sm font-medium">Support email</label><input id="support_email" name="support_email" type="email" maxlength="255" value="{{ old('support_email', $settings['support_email']) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <div><label for="support_phone" class="mb-1 block text-sm font-medium">Support phone</label><input id="support_phone" name="support_phone" type="tel" maxlength="30" value="{{ old('support_phone', $settings['support_phone']) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <div class="sm:col-span-2"><label for="maintenance_notice" class="mb-1 block text-sm font-medium">Maintenance notice</label><textarea id="maintenance_notice" name="maintenance_notice" rows="3" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('maintenance_notice', $settings['maintenance_notice']) }}</textarea><p class="mt-1 text-xs text-slate-500">Plain text only, maximum 500 characters.</p></div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><h2 class="text-base font-semibold">Windows and Android versions</h2><p class="mt-1 text-sm text-slate-500">Latest and minimum supported versions are managed in the existing app version settings.</p></div><a href="{{ route('admin.settings.app-versions') }}" class="shrink-0 rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Manage app versions</a></div><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach(['windows' => 'Windows', 'android' => 'Android'] as $platform => $label)<div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-semibold text-slate-700">{{ $label }}</p><p class="mt-1 text-xs text-slate-600">Latest: {{ $versions[$platform]['latest_version'] ?: 'Not configured' }} <span class="px-1 text-slate-300">·</span> Minimum: {{ $versions[$platform]['minimum_supported'] ?: 'Not configured' }}</p></div>@endforeach</div></section>

        <div class="flex justify-end"><button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Save router settings</button></div>
    </form>
</div>
@endsection
