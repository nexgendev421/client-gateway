<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LicenceRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Licence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LicenceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Licence::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_column(LicenceStatus::cases(), 'value'))],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $licences = Licence::query()->with('customer')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('plan', 'like', "%{$search}%")
                        ->orWhere('plan_code', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customers) => $customers->where('business_name', 'like', "%{$search}%")
                            ->orWhere('trading_name', 'like', "%{$search}%"));
                });
            })
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('updated_at')->paginate(25)->withQueryString();

        return view('admin.licences.index', compact('licences', 'filters', 'search'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Licence::class);
        $customers = Customer::query()->whereDoesntHave('licence')->where('status', '!=', 'archived')->orderBy('business_name')->get();
        $statuses = LicenceStatus::cases();
        $selectedCustomerId = (int) $request->query('customer_id', 0);

        return view('admin.licences.create', compact('customers', 'statuses', 'selectedCustomerId'));
    }

    public function store(LicenceRequest $request): RedirectResponse
    {
        $this->authorize('create', Licence::class);
        $data = $this->prepareData($request->validated());
        $data['status'] ??= LicenceStatus::Trial->value;

        $licence = DB::transaction(function () use ($data, $request): Licence {
            $licence = Licence::query()->create($data);
            $this->audit($licence, 'licence.created', $request, [
                'customer_id' => $licence->customer_id,
                'plan_code' => $licence->plan_code,
                'plan' => $licence->plan,
                'status' => $licence->status->value,
            ]);

            return $licence;
        });

        return redirect()->route('admin.licences.show', $licence)->with('status', 'Licence created.');
    }

    public function show(Licence $licence): View
    {
        $this->authorize('view', $licence);
        $licence->load('customer');
        $activity = AuditLog::query()->with('actor')->where('resource_type', Licence::class)
            ->where('resource_id', (string) $licence->getKey())->orderByDesc('occurred_at')->limit(30)->get();

        return view('admin.licences.show', compact('licence', 'activity'));
    }

    public function edit(Licence $licence): View
    {
        $this->authorize('update', $licence);
        $licence->load('customer');

        return view('admin.licences.edit', compact('licence'));
    }

    public function update(LicenceRequest $request, Licence $licence): RedirectResponse
    {
        $this->authorize('update', $licence);
        $data = $this->prepareData($request->validated());
        $changed = $this->changedFields($licence, $data);

        DB::transaction(function () use ($licence, $data, $changed, $request): void {
            $locked = Licence::query()->lockForUpdate()->findOrFail($licence->getKey());
            $before = $this->safeSnapshot($locked, $changed);
            $locked->update($data);
            $this->audit($locked, 'licence.updated', $request, [
                'changed_fields' => $changed,
                'before' => $before,
                'after' => $this->safeSnapshot($locked, $changed),
            ]);
        });

        return redirect()->route('admin.licences.show', $licence)->with('status', 'Licence updated.');
    }

    public function extend(Request $request, Licence $licence): RedirectResponse
    {
        $this->authorize('changeStatus', $licence);
        $data = $request->validate([
            'valid_until' => ['required', 'date', 'after:'.$licence->valid_until->format('Y-m-d H:i:s')],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($licence, $request, $data): void {
            $locked = Licence::query()->lockForUpdate()->findOrFail($licence->getKey());
            $oldStatus = $locked->status;
            $oldExpiry = $locked->valid_until;
            $newExpiry = Carbon::parse($data['valid_until']);
            if ($locked->grace_until !== null && $locked->grace_until->lessThanOrEqualTo($newExpiry)) {
                $locked->grace_until = null;
                if ($locked->status === LicenceStatus::Grace) {
                    $locked->status = LicenceStatus::Active;
                }
            }
            $locked->valid_until = $newExpiry;
            $locked->save();
            $this->audit($locked, 'licence.extended', $request, [
                'from_valid_until' => $oldExpiry->toIso8601String(),
                'to_valid_until' => $locked->valid_until->toIso8601String(),
                'from_status' => $oldStatus->value,
                'to_status' => $locked->status->value,
                'reason' => $this->safeReason($data['reason']),
            ]);
        });

        return redirect()->route('admin.licences.show', $licence)->with('status', 'Licence expiry extended.');
    }

    public function transition(Request $request, Licence $licence, string $state): RedirectResponse
    {
        $this->authorize('changeStatus', $licence);
        $targets = [
            'past-due' => LicenceStatus::PastDue,
            'grace' => LicenceStatus::Grace,
            'active' => LicenceStatus::Active,
            'restricted' => LicenceStatus::Restricted,
            'suspended' => LicenceStatus::Suspended,
            'cancelled' => LicenceStatus::Cancelled,
        ];
        abort_unless(isset($targets[$state]), 404);
        $target = $targets[$state];
        $rules = ['reason' => ['required', 'string', 'min:3', 'max:500']];
        if ($target === LicenceStatus::Grace) {
            $rules['grace_until'] = ['required', 'date', 'after:'.$licence->valid_until->format('Y-m-d H:i:s')];
        }
        $data = $request->validate($rules);

        DB::transaction(function () use ($licence, $request, $data, $target): void {
            $locked = Licence::query()->lockForUpdate()->findOrFail($licence->getKey());
            abort_unless($locked->status !== $target, 409);
            if ($target === LicenceStatus::Active && $locked->valid_until->isPast()) {
                throw ValidationException::withMessages(['reason' => 'Extend the licence expiry before restoring active status.']);
            }
            if ($target === LicenceStatus::Grace) {
                $locked->grace_until = $data['grace_until'];
            }
            $oldStatus = $locked->status;
            $locked->status = $target;
            $locked->save();
            $action = match ($target) {
                LicenceStatus::PastDue => 'licence.past_due',
                LicenceStatus::Grace => 'licence.grace_configured',
                LicenceStatus::Active => 'licence.restored_active',
                LicenceStatus::Restricted => 'licence.restricted',
                LicenceStatus::Suspended => 'licence.suspended',
                LicenceStatus::Cancelled => 'licence.cancelled',
                default => 'licence.status_changed',
            };
            $this->audit($locked, $action, $request, [
                'from_status' => $oldStatus->value,
                'to_status' => $locked->status->value,
                'grace_until' => $locked->grace_until?->toIso8601String(),
                'reason' => $this->safeReason($data['reason']),
            ]);
        });

        return redirect()->route('admin.licences.show', $licence)->with('status', 'Licence status updated.');
    }

    private function prepareData(array $data): array
    {
        if (array_key_exists('metadata_json', $data)) {
            $data['metadata'] = $data['metadata_json'] === null || $data['metadata_json'] === ''
                ? null
                : json_decode($data['metadata_json'], true, 32, JSON_THROW_ON_ERROR);
            unset($data['metadata_json']);
        }

        if (array_key_exists('status', $data) && $data['status'] === null) {
            unset($data['status']);
        }

        return $data;
    }

    private function changedFields(Licence $licence, array $data): array
    {
        return collect($data)->filter(function (mixed $value, string $field) use ($licence): bool {
            $current = $licence->getAttribute($field);
            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            } elseif ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d H:i:s');
                $value = date('Y-m-d H:i:s', strtotime((string) $value));
            }

            return $current !== $value;
        })->keys()->values()->all();
    }

    private function safeSnapshot(Licence $licence, array $fields): array
    {
        $snapshot = [];
        foreach ($fields as $field) {
            $value = $licence->getAttribute($field);
            $snapshot[$field] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
                is_array($value) => ['changed' => true],
                default => $value,
            };
        }

        return $snapshot;
    }

    private function audit(Licence $licence, string $action, Request $request, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'resource_type' => Licence::class,
            'resource_id' => (string) $licence->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }

    private function safeReason(string $reason): string
    {
        $reason = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $reason) ?? '';
        $reason = preg_replace('/\bsypd_[A-Za-z0-9_-]{24,}/i', '[credential redacted]', $reason) ?? '';
        $reason = preg_replace('/\b[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', '[signed token redacted]', $reason) ?? '';
        $reason = preg_replace('/\b\d{4}-?\d{4}\b/', '[activation code redacted]', $reason) ?? '';
        $reason = preg_replace('/\b(password|secret|token|activation[ _-]?code|credential|pin)\b\s*[:=]\s*\S+/i', '$1=[redacted]', $reason) ?? '';
        $reason = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $reason) ?? '';

        return mb_substr(trim($reason), 0, 500);
    }
}
