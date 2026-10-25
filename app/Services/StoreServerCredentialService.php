<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\StoreStatus;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreServerCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StoreServerCredentialService
{
    /** @return array{credential:StoreServerCredential,token:string} */
    public function issue(Store $store, User $actor, ?string $ip, bool $rotate = false): array
    {
        return DB::transaction(function () use ($store, $actor, $ip, $rotate): array {
            $lockedStore = Store::query()->lockForUpdate()->findOrFail($store->getKey());
            $active = $lockedStore->serverCredentials()->whereNull('revoked_at');
            if ($rotate) {
                $active->update(['revoked_at' => now(), 'updated_at' => now()]);
            } elseif ($active->exists()) {
                throw new StoreServerCredentialException;
            }

            $token = 'syps_'.rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
            $credential = $lockedStore->serverCredentials()->create([
                'token_hash' => Hash::make($token),
                'token_prefix' => substr($token, 0, 16),
                'created_by' => $actor->getKey(),
            ]);

            AuditLog::query()->create([
                'actor_id' => $actor->getKey(),
                'action' => $rotate ? 'store.licence_credential_rotated' : 'store.licence_credential_issued',
                'resource_type' => Store::class,
                'resource_id' => (string) $lockedStore->getKey(),
                'metadata' => ['credential_id' => $credential->getKey()],
                'ip_address' => $ip,
                'occurred_at' => now(),
            ]);

            return ['credential' => $credential, 'token' => $token];
        });
    }

    public function authenticate(string $token): ?StoreServerCredential
    {
        if (strlen($token) < 40 || strlen($token) > 100 || ! str_starts_with($token, 'syps_')) {
            return null;
        }

        $candidate = StoreServerCredential::query()
            ->where('token_prefix', substr($token, 0, 16))
            ->whereNull('revoked_at')
            ->first(['id', 'store_id']);
        if ($candidate === null) {
            return null;
        }

        return DB::transaction(function () use ($candidate, $token): ?StoreServerCredential {
            $credential = StoreServerCredential::query()->lockForUpdate()->find($candidate->getKey());
            if ($credential === null || $credential->revoked_at !== null || ! Hash::check($token, $credential->token_hash)) {
                return null;
            }
            $credential->load('store.customer.licence');
            if ($credential->store->status !== StoreStatus::Active
                || $credential->store->customer->status !== CustomerStatus::Active) {
                return null;
            }
            $credential->forceFill(['last_used_at' => now()])->save();

            return $credential;
        });
    }
}
