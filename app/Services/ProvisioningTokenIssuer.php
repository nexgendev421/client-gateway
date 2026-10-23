<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Store;

class ProvisioningTokenIssuer
{
    public function __construct(private readonly AsymmetricJwtSigner $signer) {}

    /** @return array{token: string, expires_at: string} */
    public function issue(Store $store, Device $device): array
    {
        $ttl = max(60, min(600, (int) config('provisioning.ttl_seconds', 300)));
        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + $ttl;
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

        $token = $this->signer->sign($claims);

        return [
            'token' => $token,
            'expires_at' => now()->setTimestamp($expiresAt)->toIso8601String(),
        ];
    }
}
