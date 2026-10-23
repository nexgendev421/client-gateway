@extends('layouts.admin', ['activeNav' => 'admin-users'])

@section('title', 'Admin Users')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Security</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Admin users</h1><p class="mt-1 text-sm text-slate-500">Manage Router access, roles, and account status.</p></div>
        <a href="{{ route('admin.admin-users.create') }}" class="inline-flex justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Add admin user</a>
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_180px_160px_auto]">
        <input name="q" value="{{ $search }}" placeholder="Search name or email" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="role" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All roles</option>@foreach(\App\Enums\AdminRole::cases() as $role)<option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ str($role->value)->replace('_', ' ')->headline() }}</option>@endforeach</select>
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach(\App\Enums\AdminStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->headline() }}</option>@endforeach</select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if($users->isEmpty())<p class="px-5 py-12 text-center text-sm text-slate-500">No admin users match this search.</p>@else
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Admin</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th><th class="px-5 py-3 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach($users as $adminUser)<tr><td class="px-5 py-3"><p class="font-medium text-slate-900">{{ $adminUser->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $adminUser->email }}</p></td><td class="px-4 py-3 capitalize">{{ str($adminUser->role->value)->replace('_', ' ') }}</td><td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs {{ $adminUser->status === \App\Enums\AdminStatus::Active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ str($adminUser->status->value)->headline() }}</span></td><td class="px-4 py-3 text-xs text-slate-500">{{ $adminUser->created_at->format('j M Y') }}</td><td class="px-5 py-3 text-right"><a href="{{ route('admin.admin-users.edit', $adminUser) }}" class="font-medium text-blue-700 hover:text-blue-900">Manage</a></td></tr>@endforeach
            </tbody></table></div>
            @if($users->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $users->links() }}</div>@endif
        @endif
    </section>
@endsection
