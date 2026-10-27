<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Sign in · SiyazPOS Router</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-12">
        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-700 text-lg font-bold tracking-tight text-white shadow-sm">S</div>
                <p class="text-sm font-semibold tracking-wide text-blue-700">SIYAZPOS</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Router</h1>
                <p class="mt-2 text-sm text-slate-500">Sign in to manage your Router service</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-8">
                <h2 class="text-lg font-semibold">Admin sign in</h2>
                <p class="mt-1 text-sm text-slate-500">Enter your administrator credentials to continue.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
                    </div>
                    <label for="remember" class="flex items-center gap-2 text-sm text-slate-600">
                        <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                        Remember me on this device
                    </label>
                    <button type="submit" class="flex w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">Sign in</button>
                </form>
            </div>
            <p class="mt-6 text-center text-xs text-slate-400">Secure access for authorised SiyazPOS Router administrators</p>
        </section>
    </main>
</body>
</html>
