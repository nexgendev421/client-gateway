@extends('layouts.admin')

@section('title', 'MFA recovery codes')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div><p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Account security</p><h1 class="mt-1 text-2xl font-semibold">Save your recovery codes</h1></div>
    <section class="rounded-xl border border-amber-200 bg-amber-50 p-6"><p class="text-sm text-amber-950">Each code can be used once if you cannot access your authenticator. Save these now. They will not be shown again.</p>
        <ul class="mt-5 grid grid-cols-1 gap-2 font-mono text-sm sm:grid-cols-2">@foreach($recoveryCodes as $code)<li class="rounded-lg border border-amber-200 bg-white px-3 py-2">{{ $code }}</li>@endforeach</ul>
        <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Continue to dashboard</a>
    </section>
</div>
@endsection
