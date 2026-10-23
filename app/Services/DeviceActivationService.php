<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\LicenceStatus;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Licence;
use App\Models\Store;

class DeviceActivationService
{
    public function __construct(
        private readonly ActivationCodeService $activationCodes,
        private readonly PosEndpointGuard $endpointGuard,
        private readonly ProvisioningTokenIssuer $tokenIssuer,
    ) {}

    /** @param array{store_code:string,activation_code:string,device_id:string,device_name:string,platform:string,app_version:string} $data */
    public function activate(array $data, ?string $ipAddress): ?array
    {
        return $this->activationCodes->redeemThen(
            $data['store_code'],
            $data['activation_code'],
            $data['device_name'],
            $ipAddress,
            function (Store $candidateStore, ActivationCode $activation) use ($data, $ipAddress): array {
                $customer = Customer::query()->whereKey($candidateStore->customer_id)->lockForUpdate()->first();
                $store = Store::query()->whereKey($candidateStore->getKey())->lockForUpdate()->first();
                if (! $customer || ! $store || $customer->status->value !== 'active' || $store->status->value !== 'active') {
                    throw new ActivationRejected;
                }

                $store->setRelation('customer', $customer->load('licence'));
                $endpointError = $this->endpointGuard->validationError($store->endpoint_url, config('app.env') === 'production');
                if ($endpointError !== null || Device::query()->where('public_id', $data['device_id'])->exists()) {
                    throw new ActivationRejected;
                }

                $licence = $customer->licence;
                if (! $this->licenceAllowsRegistration($licence)) {
                    throw new ActivationRejected;
                }

                $storeDeviceCount = $store->devices()->where('status', '!=', DeviceStatus::Revoked->value)->count();
                $customerDeviceCount = Device::query()
                    ->whereHas('store', fn ($query) => $query->where('customer_id', $customer->getKey()))
                    ->where('status', '!=', DeviceStatus::Revoked->value)
                    ->count();

                if ($storeDeviceCount >= $store->max_devices
                    || ($licence->max_devices !== null && $customerDeviceCount >= $licence->max_devices)) {
                    throw new ActivationRejected;
                }

                $device = $store->devices()->create([
                    'public_id' => $data['device_id'],
                    'name' => $data['device_name'],
                    'platform' => $data['platform'],
                    'app_version' => $data['app_version'],
                    'status' => DeviceStatus::Active,
                    'registered_at' => now(),
                    'last_seen_at' => now(),
                    'last_ip' => $ipAddress,
                ]);

                $token = $this->tokenIssuer->issue($store, $device);
                AuditLog::query()->create([
                    'actor_id' => null,
                    'action' => 'device.provisioned',
                    'resource_type' => Device::class,
                    'resource_id' => (string) $device->getKey(),
                    'metadata' => ['store_id' => $store->getKey(), 'platform' => $device->platform->value],
                    'ip_address' => $ipAddress,
                    'occurred_at' => now(),
                ]);

                return [
                    'business_name' => $customer->trading_name ?: $customer->business_name,
                    'store_name' => $store->name,
                    'endpoint_url' => $store->endpoint_url,
                    'device_reference' => $device->public_id,
                    'provisioning_token' => $token['token'],
                    'expires_at' => $token['expires_at'],
                ];
            },
        );
    }

    private function licenceAllowsRegistration(?Licence $licence): bool
    {
        if ($licence === null || $licence->starts_at->isFuture()) {
            return false;
        }

        return match ($licence->status) {
            LicenceStatus::Trial, LicenceStatus::Active, LicenceStatus::PastDue => $licence->valid_until->isFuture(),
            LicenceStatus::Grace => $licence->grace_until?->isFuture() ?? false,
            LicenceStatus::Restricted, LicenceStatus::Suspended, LicenceStatus::Cancelled => false,
        };
    }
}
