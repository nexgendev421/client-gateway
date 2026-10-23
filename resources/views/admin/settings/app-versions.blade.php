@extends('layouts.admin', ['activeNav' => 'settings'])

@section('title', 'App Versions')

@section('content')
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Router administration · Settings</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">App version management</h1>
        <p class="mt-1 text-sm text-slate-500">Publish current client versions and update guidance for Windows and Android devices.</p>
    </div>

    @if (session('status'))<div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.app-versions.update') }}" class="space-y-5">
        @csrf @method('PUT')
        @foreach (['windows' => 'Windows', 'android' => 'Android'] as $platform => $label)
            @php($value = $versions[$platform])
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-semibold text-slate-900">{{ $label }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label for="{{ $platform }}-latest" class="block text-xs font-medium text-slate-600">Latest version</label><input id="{{ $platform }}-latest" name="versions[{{ $platform }}][latest_version]" value="{{ old("versions.{$platform}.latest_version", $value['latest_version']) }}" required maxlength="40" placeholder="1.2.3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@error("versions.{$platform}.latest_version")<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                    <div><label for="{{ $platform }}-minimum" class="block text-xs font-medium text-slate-600">Minimum supported version</label><input id="{{ $platform }}-minimum" name="versions[{{ $platform }}][minimum_supported_version]" value="{{ old("versions.{$platform}.minimum_supported_version", $value['minimum_supported']) }}" required maxlength="40" placeholder="1.0.0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@error("versions.{$platform}.minimum_supported_version")<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label for="{{ $platform }}-url" class="block text-xs font-medium text-slate-600">Update or download URL (HTTPS)</label><input id="{{ $platform }}-url" type="url" name="versions[{{ $platform }}][download_url]" value="{{ old("versions.{$platform}.download_url", $value['download_url']) }}" required maxlength="2048" placeholder="https://downloads.example.com/siyazpos" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@error("versions.{$platform}.download_url")<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label for="{{ $platform }}-notes" class="block text-xs font-medium text-slate-600">Release notes summary</label><textarea id="{{ $platform }}-notes" name="versions[{{ $platform }}][release_notes]" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old("versions.{$platform}.release_notes", $value['release_notes']) }}</textarea>@error("versions.{$platform}.release_notes")<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                    <label class="flex items-start gap-2 text-sm text-slate-700 sm:col-span-2"><input type="hidden" name="versions[{{ $platform }}][mandatory_update]" value="0"><input type="checkbox" name="versions[{{ $platform }}][mandatory_update]" value="1" @checked(old("versions.{$platform}.mandatory_update", $value['mandatory_update'])) class="mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-600"><span><span class="font-medium">Require an update</span><span class="mt-0.5 block text-xs text-slate-500">Clients can use this flag to require the update before continuing.</span></span></label>
                </div>
            </section>
        @endforeach
        <div class="flex justify-end"><button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Save app versions</button></div>
    </form>
@endsection
