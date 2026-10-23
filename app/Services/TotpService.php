<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function makeSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        return $this->matchingStep($secret, $code, $timestamp, $window) !== null;
    }

    public function matchingStep(string $secret, string $code, ?int $timestamp = null, int $window = 1): ?int
    {
        if (preg_match('/^\d{6}$/D', $code) !== 1) {
            return null;
        }

        $timestamp ??= time();
        $offsets = [0];
        for ($offset = 1; $offset <= $window; $offset++) {
            $offsets[] = -$offset;
            $offsets[] = $offset;
        }
        foreach ($offsets as $step) {
            $counter = intdiv($timestamp, 30) + $step;
            if (hash_equals($this->codeAt($secret, $counter * 30), $code)) {
                return $counter;
            }
        }

        return null;
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = (string) config('app.name', 'SiyazPOS Router');
        $label = rawurlencode($issuer.':'.$user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<string> */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        for ($index = 0; $index < $count; $index++) {
            $raw = '';
            for ($position = 0; $position < 12; $position++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4);
        }

        return $codes;
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        if (strlen($normalized) !== 12) {
            return false;
        }

        foreach ($user->mfaRecoveryCodes()->whereNull('used_at')->lockForUpdate()->get() as $recoveryCode) {
            if (Hash::check($normalized, $recoveryCode->code_hash)) {
                $recoveryCode->update(['used_at' => now()]);

                return true;
            }
        }

        return false;
    }

    private function base32Encode(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $buffer = ($buffer << 8) | ord($bytes[$index]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim(str_replace('=', '', $secret), '='));
        $buffer = 0;
        $bits = 0;
        $decoded = '';
        foreach (str_split($secret) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {
                throw new \InvalidArgumentException('Invalid TOTP secret.');
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
            }
            $buffer &= (1 << $bits) - 1;
        }

        return $decoded;
    }
}
