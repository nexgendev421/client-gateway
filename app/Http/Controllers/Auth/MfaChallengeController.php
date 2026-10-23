<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MfaChallengeController extends Controller
{
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function create(Request $request): View|RedirectResponse|Response
    {
        $user = $this->pendingUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        return response()->view('auth.mfa-challenge', ['user' => $user])->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

    public function verify(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $user = $this->pendingUser($request);
        if ($user === null) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in attempt expired. Please try again.']);
        }

        $result = DB::transaction(function () use ($user, $data, $totp): array {
            $locked = User::query()->lockForUpdate()->find($user->getKey());
            if (! $locked || $locked->status !== AdminStatus::Active || ! $locked->hasMfaEnabled()) {
                return ['valid' => false, 'recovery' => false];
            }
            $step = $totp->matchingStep($locked->mfa_secret, preg_replace('/\s+/', '', $data['code']) ?? '');
            if ($step !== null && ($locked->mfa_last_used_step === null || $step > $locked->mfa_last_used_step)) {
                $locked->forceFill(['mfa_last_used_step' => $step])->save();

                return ['valid' => true, 'recovery' => false];
            }
            $recovery = $totp->consumeRecoveryCode($locked, $data['code']);

            return ['valid' => $recovery, 'recovery' => $recovery];
        });

        if (! $result['valid']) {
            return back()->withErrors(['code' => 'The verification code is invalid or has already been used.']);
        }

        if ($result['recovery']) {
            AuditLog::query()->create([
                'actor_id' => $user->getKey(),
                'action' => 'admin.mfa_recovery_code_used',
                'resource_type' => User::class,
                'resource_id' => (string) $user->getKey(),
                'metadata' => ['method' => 'recovery_code'],
                'ip_address' => $request->ip(),
                'occurred_at' => now(),
            ]);
        }

        Auth::login($user, false);
        $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_at']);
        $request->session()->put('mfa_verified_user_id', $user->getKey());
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        $startedAt = (int) $request->session()->get('mfa_pending_at', 0);
        if (! $userId || $startedAt < now()->subMinutes(10)->timestamp) {
            $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_at']);

            return null;
        }

        $user = User::query()->find($userId);
        if (! $user || $user->status !== AdminStatus::Active || ! $user->hasMfaEnabled()) {
            $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_at']);

            return null;
        }

        return $user;
    }
}
