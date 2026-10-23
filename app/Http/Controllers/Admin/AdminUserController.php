<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-router');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:'.implode(',', array_map(fn (AdminRole $role): string => $role->value, AdminRole::cases()))],
            'status' => ['nullable', 'in:'.implode(',', array_map(fn (AdminStatus $status): string => $status->value, AdminStatus::cases()))],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $users = User::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when(isset($filters['role']), fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.admin-users.index', compact('users', 'filters', 'search'));
    }

    public function create(): View
    {
        $this->authorize('manage-router');

        return view('admin.admin-users.create', ['adminUser' => new User, 'roles' => AdminRole::cases(), 'statuses' => AdminStatus::cases()]);
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        $this->authorize('manage-router');
        $data = $request->validated();
        $user = DB::transaction(function () use ($data, $request): User {
            $user = User::query()->create($data);
            $this->audit($request, $user, 'admin.created', ['role' => $user->role->value, 'status' => $user->status->value]);

            return $user;
        });

        return redirect()->route('admin.admin-users.edit', $user)->with('status', 'Admin user created.');
    }

    public function edit(User $adminUser): View
    {
        $this->authorize('manage-router');

        return view('admin.admin-users.edit', ['adminUser' => $adminUser, 'roles' => AdminRole::cases(), 'statuses' => AdminStatus::cases()]);
    }

    public function update(AdminUserRequest $request, User $adminUser): RedirectResponse
    {
        $this->authorize('manage-router');
        $data = $request->validated();

        DB::transaction(function () use ($adminUser, $data, $request): void {
            $this->lockActiveSuperAdmins();
            $locked = User::query()->lockForUpdate()->findOrFail($adminUser->getKey());
            $beforeRole = $locked->role;
            $beforeStatus = $locked->status;
            $passwordChanged = filled($data['password'] ?? null);

            if ($locked->is($request->user()) && $data['status'] === AdminStatus::Disabled->value) {
                throw ValidationException::withMessages(['status' => 'You cannot disable your own admin account.']);
            }
            $this->protectLastSuperAdmin($locked, AdminRole::from($data['role']), AdminStatus::from($data['status']));

            if (! $passwordChanged) {
                unset($data['password']);
            }
            $locked->update($data);

            $this->audit($request, $locked, 'admin.updated', ['changed_fields' => array_values(array_filter(['name', 'email', $beforeRole !== $locked->role ? 'role' : null, $beforeStatus !== $locked->status ? 'status' : null]))]);
            if ($beforeRole !== $locked->role) {
                $this->audit($request, $locked, 'admin.role_changed', ['from_role' => $beforeRole->value, 'to_role' => $locked->role->value]);
            }
            if ($beforeStatus !== $locked->status) {
                $this->audit($request, $locked, $locked->status === AdminStatus::Disabled ? 'admin.disabled' : 'admin.enabled', [
                    'from_status' => $beforeStatus->value,
                    'to_status' => $locked->status->value,
                ]);
            }
            if ($passwordChanged) {
                $this->audit($request, $locked, 'admin.password_reset', ['method' => 'super_admin_reset']);
            }
        });

        return redirect()->route('admin.admin-users.edit', $adminUser)->with('status', 'Admin user updated.');
    }

    public function disable(Request $request, User $adminUser): RedirectResponse
    {
        return $this->changeStatus($request, $adminUser, AdminStatus::Disabled);
    }

    public function enable(Request $request, User $adminUser): RedirectResponse
    {
        return $this->changeStatus($request, $adminUser, AdminStatus::Active);
    }

    private function changeStatus(Request $request, User $adminUser, AdminStatus $status): RedirectResponse
    {
        $this->authorize('manage-router');
        DB::transaction(function () use ($request, $adminUser, $status): void {
            $this->lockActiveSuperAdmins();
            $locked = User::query()->lockForUpdate()->findOrFail($adminUser->getKey());
            abort_unless($locked->status !== $status, 409);
            if ($locked->is($request->user()) && $status === AdminStatus::Disabled) {
                throw ValidationException::withMessages(['status' => 'You cannot disable your own admin account.']);
            }
            $this->protectLastSuperAdmin($locked, $locked->role, $status);
            $previous = $locked->status;
            $locked->update(['status' => $status]);
            $this->audit($request, $locked, $status === AdminStatus::Disabled ? 'admin.disabled' : 'admin.enabled', [
                'from_status' => $previous->value,
                'to_status' => $status->value,
            ]);
        });

        return redirect()->route('admin.admin-users.edit', $adminUser)->with('status', $status === AdminStatus::Disabled ? 'Admin user disabled.' : 'Admin user enabled.');
    }

    private function lockActiveSuperAdmins(): void
    {
        User::query()->where('role', AdminRole::SuperAdmin)->where('status', AdminStatus::Active)->orderBy('id')->lockForUpdate()->get();
    }

    private function protectLastSuperAdmin(User $user, AdminRole $newRole, AdminStatus $newStatus): void
    {
        if ($user->role === AdminRole::SuperAdmin && $user->status === AdminStatus::Active
            && ($newRole !== AdminRole::SuperAdmin || $newStatus !== AdminStatus::Active)
            && User::query()->where('role', AdminRole::SuperAdmin)->where('status', AdminStatus::Active)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'The last active super admin cannot be demoted or disabled.']);
        }
    }

    private function audit(Request $request, User $subject, string $action, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'resource_type' => User::class,
            'resource_id' => (string) $subject->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
