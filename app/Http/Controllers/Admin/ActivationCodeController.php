<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivationCodeRequest;
use App\Models\ActivationCode;
use App\Models\Store;
use App\Services\ActivationCodeService;
use App\Services\RouterSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ActivationCodeController extends Controller
{
    public function index(Request $request, ActivationCodeService $service): View
    {
        $this->authorize('viewAny', ActivationCode::class);
        $service->expirePastDue();
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,used,expired,revoked'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $suffixSearch = preg_replace('/[^0-9]/', '', $search);
        $status = $validated['status'] ?? '';

        $codes = ActivationCode::query()->with(['store.customer', 'creator'])
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search, $suffixSearch): void {
                $query->where(function (Builder $query) use ($search, $suffixSearch): void {
                    $query->whereHas('store', fn (Builder $store) => $store->where('name', 'like', "%{$search}%")->orWhere('store_code', 'like', "%{$search}%"))
                        ->orWhereHas('store.customer', fn (Builder $customer) => $customer->where('business_name', 'like', "%{$search}%"));

                    if ($suffixSearch !== '') {
                        $query->orWhere('masked_suffix', 'like', '%'.$suffixSearch.'%');
                    }
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activation-codes.index', compact('codes', 'search', 'status'));
    }

    public function storeIndex(Store $store, ActivationCodeService $service): View
    {
        $this->authorize('view', $store);
        $service->expirePastDue();
        $codes = $store->activationCodes()->with('creator')->orderByDesc('created_at')->paginate(20);

        return view('admin.activation-codes.store', compact('store', 'codes'));
    }

    public function create(Store $store, RouterSettings $settings): View
    {
        $this->authorize('generateActivationCode', $store);

        return view('admin.activation-codes.create', ['store' => $store, 'defaults' => $settings->all()]);
    }

    public function generate(ActivationCodeRequest $request, Store $store, ActivationCodeService $service, RouterSettings $settings): Response
    {
        $this->authorize('generateActivationCode', $store);
        $data = $request->validated();
        $generated = $service->generate(
            $store,
            (int) $data['expires_in_hours'],
            (int) ($data['max_uses'] ?? $settings->all()['default_activation_max_uses']),
            $data['intended_device_name'] ?? null,
            (int) $request->user()->getKey(),
            $request->ip(),
        );
        $record = $generated['record'];
        $code = $generated['code'];

        return response()->view('admin.stores.activation-code', compact('store', 'record', 'code'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function revoke(Request $request, ActivationCode $activationCode, ActivationCodeService $service): RedirectResponse
    {
        $this->authorize('revoke', $activationCode);
        $revoked = $service->revoke($activationCode, (int) $request->user()->getKey(), $request->ip());

        return back()->with('status', $revoked ? 'Activation code revoked.' : 'This code is no longer active.');
    }
}
