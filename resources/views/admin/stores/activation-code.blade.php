<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Activation code · SiyazPOS Router</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-5 py-12">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-sm sm:p-9">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-lg font-semibold text-cyan-800">✓</span>
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">One-time activation</p>
            <h1 class="mt-1 text-xl font-semibold">Code generated for {{ $store->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">This code expires at {{ $record->expires_at->format('H:i, j M Y') }} and can be used {{ $record->max_uses }} time{{ $record->max_uses === 1 ? '' : 's' }}.</p>
            <code class="mt-6 block select-all rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 font-mono text-2xl font-semibold tracking-[0.16em] text-slate-900">{{ $code }}</code>
            <p class="mt-3 text-xs text-amber-800">Copy it now. Only the masked suffix is stored; the full code will not be shown again.</p>
            @if($record->intended_device_name)<p class="mt-3 text-xs text-slate-500">Intended device: {{ $record->intended_device_name }}</p>@endif
            <a href="{{ route('admin.stores.activation-codes.index', $store) }}" class="mt-7 inline-flex rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">View store activation codes</a>
        </section>
    </main>
</body>
</html>
