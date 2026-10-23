@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Router administration</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $pageTitle }}</h1>
    </div>
    <section class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-700">
            <span class="text-lg font-semibold">{{ mb_substr($pageTitle, 0, 1) }}</span>
        </div>
        <h2 class="mt-4 text-base font-semibold text-slate-800">This section is not available yet</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">The {{ $pageTitle }} management tools have not been added. Return to the dashboard for the current Router overview.</p>
        <a href="{{ route('dashboard') }}" class="mt-5 inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Go to dashboard</a>
    </section>
@endsection
