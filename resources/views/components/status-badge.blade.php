@props(['status', 'label' => null])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $tone = match ($value) {
        'active', 'online', 'trial' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'grace', 'past_due', 'disabled', 'suspended' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'offline', 'restricted', 'revoked', 'archived' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
        'used' => 'bg-blue-50 text-blue-700 ring-blue-600/15',
        'expired', 'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/15',
    };
    $display = $label ?? str($value)->replace('_', ' ')->headline();
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', $tone]) }}>{{ $display }}</span>
