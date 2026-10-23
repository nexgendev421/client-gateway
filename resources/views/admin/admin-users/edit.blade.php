@extends('layouts.admin', ['activeNav' => 'admin-users'])

@section('title', 'Manage Admin User')

@section('content')
    <div class="mb-5"><a href="{{ route('admin.admin-users.index') }}" class="text-xs font-medium text-blue-700">← Admin users</a></div>
    <section class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @if(session('status'))<div role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        <h1 class="text-xl font-semibold">Manage admin user</h1><p class="mt-1 text-sm text-slate-500">Update account details, role, status, or set a new password.</p>
        <form method="POST" action="{{ route('admin.admin-users.update', $adminUser) }}" class="mt-6">@csrf @method('PUT')
            @include('admin.admin-users.form', ['creating' => false])
        </form>
        @if($adminUser->status === \App\Enums\AdminStatus::Active && ! $adminUser->is(auth()->user()))
            <form method="POST" action="{{ route('admin.admin-users.disable', $adminUser) }}" class="mt-5 border-t border-slate-100 pt-5" onsubmit="return confirm('Disable this admin account?')">@csrf<button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Disable account</button></form>
        @elseif($adminUser->status === \App\Enums\AdminStatus::Disabled)
            <form method="POST" action="{{ route('admin.admin-users.enable', $adminUser) }}" class="mt-5 border-t border-slate-100 pt-5">@csrf<button class="rounded-lg border border-emerald-200 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Enable account</button></form>
        @endif
    </section>
@endsection
