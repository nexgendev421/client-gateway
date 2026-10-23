<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DeviceStatus;
use App\Enums\StoreStatus;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeviceCredentialService
{
    /** @return array{credential: DeviceCredential, token: string} */
    public function issue(Device $device, ?User $actor = null, ?string $ipAddress = null, bool $rotate = false): array
    {
        return DB::transaction(function () use ($device, $actor, $ipAddress, $rotate): array {
            $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            if ($lockedDevice->status !== DeviceStatus::Active) {
                throw new DeviceCredentialException;
            }

            $active = $lockedDevice->credentials()->whereNull('revoked_at')->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
            if ($rotate) {
                $active->update(['revoked_at' => now(), 'updated_at' => now()]);
            } elseif ($active->exists()) {
                throw new DeviceCredentialException;
            }

            $plainToken = 'sypd_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $credential = $lockedDevice->credentials()->create([
                'token_hash' => Hash::make($plainToken),
                'token_prefix' => substr($plainToken, 0, 12),
                'expires_at' => null,
            ]);

            $this->audit($lockedDevice, $rotate ? 'device.credential_rotated' : 'device.credential_issued', $actor?->getKey(), $ipAddress, [
                'credential_id' => $credential->getKey(),
            ]);

            return ['credential' => $credential, 'token' => $plainToken];
        });
    }

    public function revoke(DeviceCredential $credential, ?User $actor = null, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($credential, $actor, $ipAddress): void {
            $locked = DeviceCredential::query()->lockForUpdate()->with('device')->findOrFail($credential->getKey());
            if ($locked->revoked_at !== null) {
                return;
            }

            $locked->update(['revoked_at' => now()]);
            $this->audit($locked->device, 'device.credential_revoked', $actor?->getKey(), $ipAddress, [
                'credential_id' => $locked->getKey(),
            ]);
        });
    }

    public function authenticate(string $plainToken): ?DeviceCredential
    {
        if (strlen($plainToken) < 40 || strlen($plainToken) > 80 || ! str_starts_with($plainToken, 'sypd_')) {
            return null;
        }

        $candidates = DeviceCredential::query()
            ->where('token_prefix', substr($plainToken, 0, 12))
            ->whereNull('revoked_at')
            ->limit(10)
            ->get(['id', 'device_id']);

        foreach ($candidates as $candidate) {
            $authenticated = DB::transaction(function () use ($candidate, $plainToken): ?DeviceCredential {
                $device = Device::query()->with('store.customer')->lockForUpdate()->find($candidate->device_id);
                $credential = DeviceCredential::query()->lockForUpdate()->find($candidate->getKey());
                if ($credential === null || $credential->revoked_at !== null
                    || ($credential->expires_at !== null && $credential->expires_at->isPast())
                    || ! Hash::check($plainToken, $credential->token_hash)) {
                    return null;
                }

                if ($device === null || $device->status !== DeviceStatus::Active
                    || $device->store->status !== StoreStatus::Active
                    || $device->store->customer->status !== CustomerStatus::Active) {
                    return null;
                }

                $credential->forceFill(['last_used_at' => now()])->save();
                $credential->setRelation('device', $device);

                return $credential;
            });

            if ($authenticated !== null) {
                return $authenticated;
            }
        }

        return null;
    }

    private function audit(Device $device, string $action, ?int $actorId, ?string $ipAddress, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => Device::class,
            'resource_id' => (string) $device->getKey(),
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'occurred_at' => now(),
        ]);
    }
}
