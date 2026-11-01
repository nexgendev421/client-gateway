<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DeviceStatus;
use App\Enums\StoreStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

final class DeviceProvisioningTokenService
{
    public function __construct(private readonly ProvisioningTokenIssuer $tokens) {}

    /** @return array{device_reference:string,provisioning_token:string,expires_at:string} */
    public function issueFor(Device $authenticatedDevice, ?string $ipAddress): array
    {
        return DB::transaction(function () use ($authenticatedDevice, $ipAddress): array {
            $device = Device::query()->lockForUpdate()->find($authenticatedDevice->getKey());
            if ($device === null || $device->status !== DeviceStatus::Active) {
                throw new DeviceCredentialException;
            }

            $store = Store::query()->lockForUpdate()->find($device->store_id);
            if ($store === null || $store->status !== StoreStatus::Active) {
                throw new DeviceCredentialException;
            }

            $customer = Customer::query()->lockForUpdate()->find($store->customer_id);
            if ($customer === null || $customer->status !== CustomerStatus::Active) {
                throw new DeviceCredentialException;
            }

            $issued = $this->tokens->issue($store, $device);
            AuditLog::query()->create([
                'actor_id' => null,
                'action' => 'device.provisioning_token_issued',
                'resource_type' => Device::class,
                'resource_id' => (string) $device->getKey(),
                'metadata' => ['device_reference' => $device->public_id],
                'ip_address' => $ipAddress,
                'occurred_at' => now(),
            ]);

            return [
                'device_reference' => $device->public_id,
                'provisioning_token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ];
        });
    }
}
