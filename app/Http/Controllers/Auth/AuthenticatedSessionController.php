<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $credentials['status'] = AdminStatus::Active->value;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->recordLoginEvent(null, 'admin.login_failed', $request, ['reason' => 'credentials_rejected']);

            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();
        if ($user->hasMfaEnabled()) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put([
                'mfa_pending_user_id' => $user->getKey(),
                'mfa_pending_at' => now()->timestamp,
            ]);

            return redirect()->route('mfa.challenge');
        }

        $request->session()->regenerate();
        $this->recordLoginEvent($user, 'admin.login_succeeded', $request, ['mfa_verified' => false]);
        if ($user->role === AdminRole::SuperAdmin && config('security.require_super_admin_mfa')) {
            return redirect()->route('admin.mfa.setup');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()) {
            $this->recordLoginEvent($request->user(), 'admin.logout', $request, []);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function recordLoginEvent(?User $actor, string $action, Request $request, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'resource_type' => User::class,
            'resource_id' => $actor ? (string) $actor->getKey() : null,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
