<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light">
    <title>Verify sign in · SiyazPOS Router</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<main class="flex min-h-screen items-center justify-center px-5 py-12">
    <section class="w-full max-w-md">
        <div class="mb-8 text-center"><div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-700 text-lg font-bold text-white">S</div><p class="text-sm font-semibold tracking-wide text-blue-700">SIYAZPOS</p><h1 class="mt-1 text-2xl font-semibold">Verify your sign in</h1><p class="mt-2 text-sm text-slate-500">Enter the six-digit code from your authenticator app, or a recovery code.</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-8">
            @if ($errors->any())<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('mfa.challenge.verify') }}" class="space-y-5">@csrf
                <div><label for="code" class="mb-1.5 block text-sm font-medium">Verification code</label><input id="code" name="code" inputmode="text" autocomplete="one-time-code" autofocus required maxlength="64" class="block w-full rounded-lg border border-slate-300 px-3.5 py-3 text-center text-lg tracking-widest outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100"></div>
                <button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Continue</button>
            </form>
            <form method="POST" action="{{ route('mfa.challenge.cancel') }}" class="mt-4 text-center">@csrf<button class="text-sm text-slate-500 hover:text-slate-800">Cancel and sign out</button></form>
        </div>
    </section>
</main>
</body></html>
