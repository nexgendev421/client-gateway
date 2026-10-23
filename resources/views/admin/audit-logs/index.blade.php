@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="space-y-6">
    <div><p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Security and activity</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Audit Logs</h1><p class="mt-2 text-sm text-slate-500">Review administrative and device lifecycle events recorded by SiyazPOS Router.</p></div>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2"><label for="q" class="mb-1 block text-xs font-medium text-slate-600">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Action, resource, ID or admin" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-100"></div>
            <div><label for="action" class="mb-1 block text-xs font-medium text-slate-600">Action</label><select id="action" name="action" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $action)) }}</option>@endforeach</select></div>
            <div><label for="resource" class="mb-1 block text-xs font-medium text-slate-600">Resource</label><select id="resource" name="resource" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All resources</option>@foreach($resources as $key => $label)<option value="{{ $key }}" @selected(($filters['resource'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="actor_id" class="mb-1 block text-xs font-medium text-slate-600">Actor</label><select id="actor_id" name="actor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All actors</option>@foreach($actors as $actor)<option value="{{ $actor->id }}" @selected((string)($filters['actor_id'] ?? '') === (string)$actor->id)>{{ $actor->name }}</option>@endforeach<option value="0" @selected(($filters['actor_id'] ?? null) === '0')>System / device</option></select></div>
            <div class="grid grid-cols-2 gap-2"><div><label for="from" class="mb-1 block text-xs font-medium text-slate-600">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"></div><div><label for="to" class="mb-1 block text-xs font-medium text-slate-600">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"></div></div>
        </div>
        @if($errors->any())<p role="alert" class="mt-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
        <div class="mt-4 flex items-center gap-3"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Apply filters</button><a href="{{ route('admin.audit-logs.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Clear</a><span class="ml-auto text-xs text-slate-500">{{ $events->total() }} events</span></div>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        @if($events->isEmpty())
            <div class="px-6 py-16 text-center"><p class="text-sm font-medium text-slate-800">No audit events found</p><p class="mt-1 text-sm text-slate-500">Events matching these filters will appear here.</p></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3 font-semibold">When</th><th class="px-5 py-3 font-semibold">Actor</th><th class="px-5 py-3 font-semibold">Action</th><th class="px-5 py-3 font-semibold">Resource</th><th class="px-5 py-3 font-semibold">IP address</th><th class="px-5 py-3 font-semibold">Details</th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach($events as $event)
                    <tr class="align-top"><td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $event->occurred_at?->format('Y-m-d H:i:s') ?? '—' }}</td><td class="px-5 py-4"><span class="font-medium text-slate-800">{{ $event->actor?->name ?? 'System / device' }}</span>@if($event->actor)<span class="mt-0.5 block text-xs text-slate-500">{{ $event->actor->email }}</span>@endif</td><td class="px-5 py-4"><span class="font-medium text-slate-800">{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $event->action)) }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $event->action }}</span></td><td class="px-5 py-4 text-slate-600">{{ $event->resource_type ? \Illuminate\Support\Str::headline(class_basename($event->resource_type)) : '—' }}@if($event->resource_id)<span class="mt-0.5 block font-mono text-xs text-slate-500">#{{ $event->resource_id }}</span>@endif</td><td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500">{{ $event->ip_address ?: '—' }}</td><td class="px-5 py-4">@if($event->metadata)<details><summary class="cursor-pointer text-xs font-medium text-blue-700">View safe details</summary><pre class="mt-2 max-w-sm overflow-x-auto whitespace-pre-wrap break-words rounded bg-slate-50 p-2 text-xs text-slate-600">{{ json_encode($event->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@else<span class="text-slate-400">—</span>@endif</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $events->links() }}</div>
        @endif
    </section>
</div>
@endsection
