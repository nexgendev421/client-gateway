<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
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

        if (! Auth::attempt($credentials, false)) {
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
        if ($user->role === AdminRole::SuperAdmin) {
            return redirect()->route('admin.mfa.setup');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
