<?php

namespace App\Services;

use JsonException;

/** Verifies Gateway provisioning JWTs using a key selected by its public `kid`. */
class ProvisioningTokenVerifier
{
    /**
     * @return array<string, mixed>
     *
     * @throws ProvisioningTokenVerificationException
     */
    public function verify(string $token, string $expectedPurpose = 'device_provisioning'): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ProvisioningTokenVerificationException;
        }

        try {
            $header = json_decode($this->base64UrlDecode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
            $claims = json_decode($this->base64UrlDecode($parts[1]), true, 32, JSON_THROW_ON_ERROR);
            $signature = $this->base64UrlDecode($parts[2]);
        } catch (JsonException|\InvalidArgumentException) {
            throw new ProvisioningTokenVerificationException;
        }

        if (! is_array($header) || ! is_array($claims)
            || ($header['alg'] ?? null) !== 'RS256'
            || ($header['typ'] ?? null) !== 'JWT'
            || ! is_string($header['kid'] ?? null)
            || $header['kid'] === ''
            || ! is_string($claims['iss'] ?? null)
            || ! hash_equals((string) config('provisioning.issuer'), $claims['iss'])
            || ($claims['aud'] ?? null) !== 'customer-pos'
            || ($claims['purpose'] ?? null) !== $expectedPurpose
            || ! is_string($claims['store_code'] ?? null)
            || $claims['store_code'] === ''
            || ! is_string($claims['device_id'] ?? null)
            || $claims['device_id'] === ''
            || ! is_string($claims['jti'] ?? null)
            || $claims['jti'] === ''
            || ! is_int($claims['iat'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || $claims['exp'] <= $claims['iat']) {
            throw new ProvisioningTokenVerificationException;
        }

        $now = now()->timestamp;
        $skew = max(0, min(120, (int) config('provisioning.clock_skew_seconds', 30)));
        if ($claims['iat'] > $now + $skew || $claims['exp'] <= $now - $skew) {
            throw new ProvisioningTokenVerificationException;
        }

        $publicKeyPath = config('provisioning.public_keys.'.$header['kid']);
        $publicKeyPem = is_string($publicKeyPath) && $publicKeyPath !== '' ? @file_get_contents($publicKeyPath) : false;
        if (! is_string($publicKeyPem) || $publicKeyPem === '') {
            throw new ProvisioningTokenVerificationException;
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        $valid = $publicKey !== false && openssl_verify(
            $parts[0].'.'.$parts[1],
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        ) === 1;

        if (! $valid) {
            throw new ProvisioningTokenVerificationException;
        }

        return $claims;
    }

    private function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new \InvalidArgumentException;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException;
        }

        return $decoded;
    }
}
