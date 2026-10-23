<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    @if ($codes->isEmpty())
        <div class="px-5 py-12 text-center"><h2 class="text-sm font-semibold text-slate-800">No activation codes found</h2><p class="mt-1 text-sm text-slate-500">Generated codes will appear here with only their masked suffix.</p></div>
    @else
        <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Code</th><th class="px-4 py-3">Store / customer</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Usage</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3">Issued by</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($codes as $activationCode)
                    @php($status = $activationCode->status->value)
                    @php($statusClass = match($status) { 'active' => 'bg-emerald-50 text-emerald-700', 'used' => 'bg-blue-50 text-blue-700', 'expired' => 'bg-slate-100 text-slate-600', default => 'bg-rose-50 text-rose-700' })
                    <tr>
                        <td class="px-5 py-3"><span class="font-mono font-semibold tracking-wide">****-{{ $activationCode->masked_suffix }}</span>@if($activationCode->intended_device_name)<p class="mt-0.5 text-xs text-slate-500">{{ $activationCode->intended_device_name }}</p>@endif</td>
                        <td class="px-4 py-3"><a href="{{ route('admin.stores.show', $activationCode->store) }}" class="font-medium text-blue-700 hover:text-blue-900">{{ $activationCode->store->name }} · {{ $activationCode->store->store_code }}</a><p class="mt-0.5 text-xs text-slate-500">{{ $activationCode->store->customer->business_name }}</p></td>
                        <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">{{ str($status)->headline() }}</span></td>
                        <td class="px-4 py-3 tabular-nums">{{ $activationCode->used_count }} / {{ $activationCode->max_uses }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $activationCode->expires_at->format('j M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $activationCode->creator?->name ?? 'System' }}</td>
                        <td class="px-5 py-3 text-right">@can('revoke', $activationCode) @if($status === 'active')<form method="POST" action="{{ route('admin.activation-codes.revoke', $activationCode) }}" onsubmit="return confirm('Revoke this activation code?')">@csrf<button class="text-xs font-semibold text-rose-700 hover:text-rose-900">Revoke</button></form>@endif @endcan</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $codes->links() }}</div>
    @endif
</section>
