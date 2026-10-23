<?php

namespace App\Http\Middleware;

use App\Enums\AdminRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMfaIsVerified
{
    public function handle(Request $request, \Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $verifiedUserId = $request->session()->get('mfa_verified_user_id');
        if ($user->hasMfaEnabled() && (int) $verifiedUserId !== (int) $user->getKey()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Please sign in again to verify your administrator account.']);
        }

        if ($user->role === AdminRole::SuperAdmin && ! $user->hasMfaEnabled()
            && ! $request->routeIs('admin.mfa.setup', 'admin.mfa.setup.begin', 'admin.mfa.setup.confirm')) {
            return redirect()->route('admin.mfa.setup');
        }

        return $next($request);
    }
}
