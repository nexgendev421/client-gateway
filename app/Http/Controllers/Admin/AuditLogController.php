<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Licence;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    private const RESOURCE_TYPES = [
        'customer' => Customer::class,
        'store' => Store::class,
        'device' => Device::class,
        'activation_code' => ActivationCode::class,
        'licence' => Licence::class,
        'admin' => User::class,
        'setting' => SystemSetting::class,
    ];

    public function index(Request $request): View
    {
        $this->authorize('manage-router');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', 'string', 'max:120', 'exists:audit_logs,action'],
            'resource' => ['nullable', 'in:'.implode(',', array_keys(self::RESOURCE_TYPES))],
            'actor_id' => ['nullable', 'integer', 'min:0'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = AuditLog::query()->with('actor');
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('action', 'like', "%{$search}%")
                    ->orWhere('resource_id', 'like', "%{$search}%")
                    ->orWhere('resource_type', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['resource'])) {
            $query->where('resource_type', self::RESOURCE_TYPES[$filters['resource']]);
        }
        if (array_key_exists('actor_id', $filters) && (int) $filters['actor_id'] === 0) {
            $query->whereNull('actor_id');
        } elseif (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        $events = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $actors = User::query()->orderBy('name')->get(['id', 'name']);
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->limit(200)->pluck('action');
        $resources = collect(self::RESOURCE_TYPES)->mapWithKeys(fn (string $class, string $key): array => [
            $key => Str::headline($key === 'admin' ? 'admin user' : str_replace('_', ' ', $key)),
        ]);

        return view('admin.audit-logs.index', compact('events', 'actors', 'actions', 'resources', 'filters') + ['activeNav' => 'audit-logs']);
    }
}
