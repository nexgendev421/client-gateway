<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\Store;

class LicenceTokenIssuer
{
    public function __construct(private readonly AsymmetricJwtSigner $signer) {}

    /** @return array{token:string,issued_at:string} */
    public function issue(Licence $licence, ?Store $store = null): array
    {
        $licence->loadMissing('customer');
        $store?->loadMissing('customer');
        $issuedAt = now()->timestamp;

        $claims = [
            'iss' => (string) config('provisioning.issuer'),
            'aud' => 'customer-pos',
            'customer_id' => (string) $licence->customer_id,
            'licence_id' => (string) $licence->getKey(),
            'plan' => $licence->plan,
            'plan_code' => $licence->plan_code,
            'status' => $licence->status->value,
            'starts_at' => $licence->starts_at->timestamp,
            'valid_until' => $licence->valid_until->timestamp,
            'grace_until' => $licence->grace_until?->timestamp,
            'emergency_extended_at' => $licence->emergency_extended_at?->timestamp,
            'max_stores' => $licence->max_stores,
            'max_devices' => $licence->max_devices,
            'features' => $this->safeFeatures($licence->metadata ?? []),
            'iat' => $issuedAt,
            'jti' => bin2hex(random_bytes(16)),
            'purpose' => 'signed_licence',
            'refresh_after' => now()->addHours(12)->timestamp,
            'offline_until' => now()->addHours((int) config('provisioning.licence_offline_grace_hours', 72))->timestamp,
        ];
        if ($store !== null) {
            if ((int) $store->customer_id !== (int) $licence->customer_id) {
                throw new ProvisioningTokenException;
            }
            $claims['store_id'] = (string) $store->getKey();
            $claims['store_code'] = $store->store_code;
        }

        $token = $this->signer->sign($claims);

        return [
            'token' => $token,
            'issued_at' => now()->setTimestamp($issuedAt)->toIso8601String(),
        ];
    }

    private function safeFeatures(array $features): array
    {
        $safe = [];
        foreach ($features as $key => $value) {
            if (is_string($key) && preg_match('/password|secret|token|credential|authorization|cookie|pin|private.?key/i', $key)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = $this->safeFeatures($value);
            } elseif (! is_string($value) || preg_match('/Bearer\s+\S+|sypd_[A-Za-z0-9_-]{24,}|\b[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b|\b\d{4}-?\d{4}\b/i', $value) !== 1) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
