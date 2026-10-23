<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DeviceStatus;
use App\Enums\StoreStatus;
use App\Models\Device;
use App\Models\ProvisioningTokenUse;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DeviceProvisioningExchangeService
{
    public function __construct(
        private readonly ProvisioningTokenVerifier $tokens,
        private readonly DeviceCredentialService $credentials,
    ) {}

    /** @return array{device_reference:string,credential:string,expires_at:null} */
    public function exchange(string $provisioningToken, ?string $ipAddress): array
    {
        try {
            $claims = $this->tokens->verify($provisioningToken);
        } catch (ProvisioningTokenVerificationException) {
            throw new DeviceCredentialException;
        }

        try {
            return DB::transaction(function () use ($claims, $ipAddress): array {
                $store = Store::query()->whereStoreCode($claims['store_code'])->lockForUpdate()->first();
                $device = Device::query()->where('public_id', $claims['device_id'])->lockForUpdate()->first();
                if ($store === null || $device === null || $device->store_id !== $store->getKey()
                    || $device->status !== DeviceStatus::Active
                    || $store->status !== StoreStatus::Active
                    || $store->customer()->where('status', CustomerStatus::Active->value)->doesntExist()) {
                    throw new DeviceCredentialException;
                }

                ProvisioningTokenUse::query()->create([
                    'device_id' => $device->getKey(),
                    'jti_hash' => hash('sha256', $claims['jti']),
                    'used_at' => now(),
                    'expires_at' => now()->setTimestamp($claims['exp']),
                ]);

                $issued = $this->credentials->issue($device, null, $ipAddress);

                return [
                    'device_reference' => $device->public_id,
                    'credential' => $issued['token'],
                    'expires_at' => null,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw new DeviceCredentialException;
        }
    }
}
