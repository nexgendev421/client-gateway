<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Store;
use RuntimeException;

class ProvisioningTokenIssuer
{
    /** @return array{token: string, expires_at: string} */
    public function issue(Store $store, Device $device): array
    {
        $privateKeyPem = config('provisioning.private_key_pem');
        if (! is_string($privateKeyPem) || $privateKeyPem === '') {
            $keyPath = config('provisioning.private_key_path');
            $privateKeyPem = is_string($keyPath) && $keyPath !== '' ? @file_get_contents($keyPath) : false;
        }

        if (! is_string($privateKeyPem) || $privateKeyPem === '' || ! function_exists('openssl_sign')) {
            throw new ProvisioningTokenException;
        }

        $ttl = max(60, min(600, (int) config('provisioning.ttl_seconds', 300)));
        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + $ttl;
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => (string) config('provisioning.key_id'),
        ];
        $claims = [
            'iss' => (string) config('provisioning.issuer'),
            'aud' => 'customer-pos',
            'store_code' => $store->store_code,
            'device_id' => $device->public_id,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
            'purpose' => 'device_provisioning',
        ];

        try {
            $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
            $encodedClaims = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
            $payload = $encodedHeader.'.'.$encodedClaims;
            $privateKey = openssl_pkey_get_private($privateKeyPem, (string) config('provisioning.private_key_passphrase', ''));
            if ($privateKey === false || ! openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new ProvisioningTokenException;
            }
        } catch (RuntimeException $exception) {
            throw $exception instanceof ProvisioningTokenException ? $exception : new ProvisioningTokenException;
        } catch (\Throwable) {
            throw new ProvisioningTokenException;
        }

        return [
            'token' => $payload.'.'.$this->base64UrlEncode($signature),
            'expires_at' => now()->setTimestamp($expiresAt)->toIso8601String(),
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
