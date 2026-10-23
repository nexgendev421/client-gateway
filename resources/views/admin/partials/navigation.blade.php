@php
    $links = [
        ['label' => 'Dashboard', 'section' => 'dashboard', 'url' => route('dashboard')],
        ['label' => 'Customers', 'section' => 'customers', 'url' => route('admin.customers.index')],
        ['label' => 'Stores', 'section' => 'stores', 'url' => route('admin.stores.index')],
        ['label' => 'Devices', 'section' => 'devices', 'url' => route('admin.devices.index')],
        ['label' => 'Activation Codes', 'section' => 'activation-codes', 'url' => route('admin.activation-codes.index')],
        ['label' => 'Licences', 'section' => 'licences', 'url' => route('admin.section', ['section' => 'licences'])],
        ['label' => 'System Health', 'section' => 'system-health', 'url' => route('admin.system-health.index')],
        ['label' => 'Audit Logs', 'section' => 'audit-logs', 'url' => route('admin.section', ['section' => 'audit-logs'])],
        ['label' => 'Admin Users', 'section' => 'admin-users', 'url' => route('admin.section', ['section' => 'admin-users'])],
        ['label' => 'Settings', 'section' => 'settings', 'url' => route('admin.section', ['section' => 'settings'])],
    ];
    $currentSection = $activeNav ?? 'dashboard';
@endphp

<nav aria-label="Main navigation" class="{{ $compact ? 'flex gap-1 overflow-x-auto pb-1' : 'space-y-1' }}">
    @foreach ($links as $link)
        @php($isActive = $currentSection === $link['section'])
        <a href="{{ $link['url'] }}" @if ($isActive) aria-current="page" @endif
            class="{{ $compact ? 'shrink-0 whitespace-nowrap px-3 py-2 text-xs' : 'w-full px-3 py-2.5 text-[13px]' }} flex items-center rounded-lg font-medium {{ $isActive ? 'bg-blue-50 text-blue-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            @if ($isActive)<span class="mr-2 h-1.5 w-1.5 rounded-full bg-cyan-500"></span>@endif
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
