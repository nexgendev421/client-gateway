<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Dashboard') · SiyazPOS Router</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:flex lg:flex-col">
            <a href="{{ route('dashboard') }}" class="flex h-[72px] items-center gap-3 border-b border-slate-200 px-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-700 text-sm font-bold text-white">S</span>
                <span><span class="block text-sm font-semibold tracking-tight">SiyazPOS</span><span class="block text-xs text-slate-500">Router</span></span>
            </a>
            <div class="px-4 pt-6">
                <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Workspace</p>
                @include('admin.partials.navigation', ['compact' => false])
            </div>
            <div class="mt-auto border-t border-slate-200 px-6 py-4 text-xs text-slate-400">SiyazPOS Router</div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="border-b border-slate-200 bg-white">
                <div class="flex h-[72px] items-center justify-between px-4 sm:px-6 lg:px-8">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 lg:hidden">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-700 text-sm font-bold text-white">S</span>
                        <span class="text-sm font-semibold">SiyazPOS Router</span>
                    </a>
                    <div class="hidden text-sm text-slate-500 lg:block">Router control centre</div>
                    <div class="flex items-center gap-3">
                        <span class="hidden text-sm text-slate-600 sm:block">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">Sign out</button>
                        </form>
                    </div>
                </div>
                <div class="border-t border-slate-100 px-3 py-2 lg:hidden">
                    @include('admin.partials.navigation', ['compact' => true])
                </div>
            </header>
            <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
