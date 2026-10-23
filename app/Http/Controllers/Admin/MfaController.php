<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MfaController extends Controller
{
    public function setup(Request $request, TotpService $totp): View|Response
    {
        $user = $request->user();
        $uri = $user->mfa_pending_secret ? $totp->provisioningUri($user, $user->mfa_pending_secret) : null;
        $qrCode = $uri === null ? null : (new SvgWriter)->write(new QrCode(
            data: $uri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 240,
            margin: 8,
        ))->getDataUri();
        $recoveryCodeCount = $user->mfaRecoveryCodes()->whereNull('used_at')->count();

        return response()->view('admin.mfa.setup', compact('user', 'uri', 'qrCode', 'recoveryCodeCount'))
            ->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

    public function begin(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->hasMfaEnabled(), 409);
        $user->forceFill(['mfa_pending_secret' => $totp->makeSecret()])->save();
        $this->audit($request, $user, 'admin.mfa_setup_started');

        return redirect()->route('admin.mfa.setup');
    }

    public function confirm(Request $request, TotpService $totp): Response|RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/D']]);
        $recoveryCodes = $totp->generateRecoveryCodes();
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $data, $recoveryCodes, $totp): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $pendingSecret = $locked->mfa_pending_secret;
            $step = $pendingSecret === null ? null : $totp->matchingStep($pendingSecret, $data['code']);
            if ($step === null) {
                throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
            }

            $locked->forceFill([
                'mfa_secret' => $pendingSecret,
                'mfa_pending_secret' => null,
                'mfa_enabled_at' => now(),
                'mfa_last_used_step' => $step,
            ])->save();
            $locked->mfaRecoveryCodes()->delete();
            foreach ($recoveryCodes as $code) {
                $locked->mfaRecoveryCodes()->create(['code_hash' => Hash::make($this->normalizeRecoveryCode($code))]);
            }
            $this->audit($request, $locked, 'admin.mfa_enabled');
        });

        $request->session()->put('mfa_verified_user_id', $user->getKey());

        return response()->view('admin.mfa.recovery-codes', compact('recoveryCodes'))
            ->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

    public function reset(Request $request, TotpService $totp): RedirectResponse
    {
        return $this->clearMfa($request, $totp, true);
    }

    public function disable(Request $request, TotpService $totp): RedirectResponse
    {
        abort_if($request->user()->role === AdminRole::SuperAdmin, 403);

        return $this->clearMfa($request, $totp, false);
    }

    private function clearMfa(Request $request, TotpService $totp, bool $reset): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'current_password'],
            'code' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $data, $totp, $reset): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            if (! $locked->hasMfaEnabled()) {
                throw ValidationException::withMessages(['code' => 'Multi-factor authentication is not enabled.']);
            }
            $validTotp = $totp->verify($locked->mfa_secret, preg_replace('/\s+/', '', $data['code']) ?? '');
            if (! $validTotp && ! $totp->consumeRecoveryCode($locked, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'The verification code is invalid or has already been used.']);
            }

            $locked->forceFill(['mfa_secret' => null, 'mfa_pending_secret' => null, 'mfa_enabled_at' => null, 'mfa_last_used_step' => null])->save();
            $locked->mfaRecoveryCodes()->delete();
            $this->audit($request, $locked, $reset ? 'admin.mfa_reset' : 'admin.mfa_disabled');
        });

        $request->session()->forget('mfa_verified_user_id');

        return redirect()->route('admin.mfa.setup')->with('status', $reset
            ? 'Multi-factor authentication was reset. Set it up again to continue.'
            : 'Multi-factor authentication was disabled.');
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }

    private function audit(Request $request, User $user, string $action): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'resource_type' => User::class,
            'resource_id' => (string) $user->getKey(),
            'metadata' => [],
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
