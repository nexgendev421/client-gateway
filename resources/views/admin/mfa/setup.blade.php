@extends('layouts.admin')

@section('title', 'Account security')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div><p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Account security</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Multi-factor authentication</h1><p class="mt-2 text-sm text-slate-500">Use an authenticator app to protect your Router administrator account.</p></div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if (!$user->hasMfaEnabled())
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold">{{ $user->mfa_pending_secret ? 'Finish setup' : 'Set up an authenticator' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Scan the QR code with an authenticator app such as Microsoft Authenticator, Google Authenticator, or 1Password.</p>
            @if (!$user->mfa_pending_secret)
                <form method="POST" action="{{ route('admin.mfa.setup.begin') }}" class="mt-5">@csrf<button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Create authenticator setup</button></form>
            @else
                <div class="mt-5 flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                    @if($qrCode)<img src="{{ $qrCode }}" alt="Authenticator setup QR code" class="h-60 w-60 rounded-lg border border-slate-200 p-2">@endif
                    <div class="space-y-2 text-sm"><p class="font-medium">Can’t scan the code?</p><p class="text-slate-500">Enter this setup key manually:</p><code class="block break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm">{{ $user->mfa_pending_secret }}</code></div>
                </div>
                <form method="POST" action="{{ route('admin.mfa.setup.confirm') }}" class="mt-6 max-w-sm space-y-3">@csrf
                    <label for="code" class="block text-sm font-medium">Enter the six-digit code from your app</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required pattern="[0-9]{6}" maxlength="6" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm tracking-widest focus:border-blue-600 focus:ring-4 focus:ring-blue-100">
                    @error('code')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Verify and enable MFA</button>
                </form>
            @endif
        </section>
    @else
        <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-6"><h2 class="font-semibold text-emerald-950">MFA is enabled</h2><p class="mt-1 text-sm text-emerald-800">Your account has {{ $recoveryCodeCount }} unused recovery codes.</p></section>
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold">Reset authenticator or recovery codes</h2><p class="mt-1 text-sm text-slate-500">Confirm your password and an authenticator or unused recovery code. You’ll need to set up MFA again after a reset.</p>
            @if ($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('admin.mfa.reset') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                <div><label class="mb-1 block text-sm font-medium" for="password">Current password</label><input id="password" type="password" name="password" autocomplete="current-password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <div><label class="mb-1 block text-sm font-medium" for="code">Authenticator or recovery code</label><input id="code" name="code" autocomplete="one-time-code" required maxlength="64" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <div class="sm:col-span-2"><button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset MFA and show new recovery codes</button></div>
            </form>
            @if ($user->role->value !== 'super_admin')
                <form method="POST" action="{{ route('admin.mfa.disable') }}" class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2">@csrf
                    <div class="sm:col-span-2"><h3 class="font-semibold">Disable MFA</h3><p class="mt-1 text-sm text-slate-500">Confirm your password and an authenticator or unused recovery code.</p></div>
                    <div><label class="mb-1 block text-sm font-medium" for="disable-password">Current password</label><input id="disable-password" type="password" name="password" autocomplete="current-password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                    <div><label class="mb-1 block text-sm font-medium" for="disable-code">Authenticator or recovery code</label><input id="disable-code" name="code" autocomplete="one-time-code" required maxlength="64" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                    <div class="sm:col-span-2"><button class="rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Disable MFA</button></div>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection
