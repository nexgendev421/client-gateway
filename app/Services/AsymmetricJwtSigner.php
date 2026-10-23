<?php

namespace App\Services;

use Throwable;

class AsymmetricJwtSigner
{
    /** @param array<string, mixed> $claims */
    public function sign(array $claims): string
    {
        $privateKeyPem = config('provisioning.private_key_pem');
        if (! is_string($privateKeyPem) || $privateKeyPem === '') {
            $keyPath = config('provisioning.private_key_path');
            $privateKeyPem = is_string($keyPath) && $keyPath !== '' ? @file_get_contents($keyPath) : false;
        }

        if (! is_string($privateKeyPem) || $privateKeyPem === '' || ! function_exists('openssl_sign')) {
            throw new ProvisioningTokenException;
        }
        if (trim((string) config('provisioning.key_id')) === '') {
            throw new ProvisioningTokenException;
        }

        try {
            $privateKey = openssl_pkey_get_private($privateKeyPem, (string) config('provisioning.private_key_passphrase', ''));
            $details = $privateKey !== false ? openssl_pkey_get_details($privateKey) : false;
            if ($privateKey === false || ! is_array($details)
                || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
                throw new ProvisioningTokenException;
            }

            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
                'kid' => (string) config('provisioning.key_id'),
            ], JSON_THROW_ON_ERROR));
            $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
            $signingInput = $header.'.'.$payload;
            if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new ProvisioningTokenException;
            }

            return $signingInput.'.'.$this->base64UrlEncode($signature);
        } catch (ProvisioningTokenException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ProvisioningTokenException;
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
