@extends('layouts.admin', ['activeNav' => 'admin-users'])

@section('title', 'Add Admin User')

@section('content')
    <div class="mb-5"><a href="{{ route('admin.admin-users.index') }}" class="text-xs font-medium text-blue-700">← Admin users</a></div>
    <section class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h1 class="text-xl font-semibold">Add admin user</h1><p class="mt-1 text-sm text-slate-500">New passwords must contain at least 12 characters, including letters and numbers.</p>
        <form method="POST" action="{{ route('admin.admin-users.store') }}" class="mt-6">@csrf
            @include('admin.admin-users.form', ['creating' => true])
        </form>
    </section>
@endsection
