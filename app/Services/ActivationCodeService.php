<?php

namespace App\Services;

use App\Enums\ActivationCodeStatus;
use App\Enums\CustomerStatus;
use App\Enums\StoreStatus;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Store;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ActivationCodeService
{
    /** @return array{record: ActivationCode, code: string} */
    public function generate(Store $store, int $expiresInHours, int $maxUses, ?string $intendedDeviceName, int $actorId, ?string $ipAddress): array
    {
        abort_unless($store->status === StoreStatus::Active && $store->customer->status === CustomerStatus::Active, 409);

        $digits = sprintf('%04d%04d', random_int(0, 9999), random_int(0, 9999));
        $code = substr($digits, 0, 4).'-'.substr($digits, 4, 4);
        $expiresAt = now()->addHours($expiresInHours);

        $record = DB::transaction(function () use ($store, $digits, $code, $expiresAt, $maxUses, $intendedDeviceName, $actorId, $ipAddress): ActivationCode {
            $record = $store->activationCodes()->create([
                'code_hash' => Hash::make($digits),
                'masked_suffix' => substr($code, -4),
                'expires_at' => $expiresAt,
                'max_uses' => $maxUses,
                'used_count' => 0,
                'status' => ActivationCodeStatus::Active,
                'intended_device_name' => $intendedDeviceName,
                'created_by' => $actorId,
            ]);

            $this->audit($record, 'activation_code.generated', $actorId, $ipAddress, [
                'masked_suffix' => $record->masked_suffix,
                'expires_at' => $expiresAt->toIso8601String(),
                'max_uses' => $maxUses,
            ]);

            return $record;
        });

        return ['record' => $record, 'code' => $code];
    }

    public function redeem(string $storeCode, string $code, ?string $deviceName, ?string $ipAddress): ?ActivationCode
    {
        return $this->redeemThen($storeCode, $code, $deviceName, $ipAddress, static fn (Store $store, ActivationCode $activation): null => null);
    }

    public function redeemThen(string $storeCode, string $code, ?string $deviceName, ?string $ipAddress, Closure $onVerified): mixed
    {
        $normalizedCode = preg_replace('/\s+/', '', trim($code));
        if (! is_string($normalizedCode) || preg_match('/^\d{4}-?\d{4}$/', $normalizedCode) !== 1) {
            return null;
        }
        $normalizedCode = str_replace('-', '', $normalizedCode);

        $store = Store::query()->with('customer')->whereStoreCode($storeCode)->first();
        if (! $store || $store->status !== StoreStatus::Active || $store->customer->status !== CustomerStatus::Active) {
            return null;
        }

        return DB::transaction(function () use ($store, $normalizedCode, $deviceName, $ipAddress, $onVerified): mixed {
            $now = now();
            $store->activationCodes()
                ->where('status', ActivationCodeStatus::Active->value)
                ->where('expires_at', '<=', $now)
                ->update(['status' => ActivationCodeStatus::Expired->value, 'updated_at' => $now]);

            $candidates = $store->activationCodes()
                ->where('status', ActivationCodeStatus::Active->value)
                ->where('expires_at', '>', $now)
                ->whereColumn('used_count', '<', 'max_uses')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $matchedId = null;
            foreach ($candidates as $candidate) {
                if (Hash::check($normalizedCode, $candidate->code_hash)) {
                    $matchedId = $candidate->getKey();
                    break;
                }
            }

            if ($matchedId === null) {
                return null;
            }

            $activation = ActivationCode::query()->whereKey($matchedId)->lockForUpdate()->first();
            if (! $activation
                || $activation->status !== ActivationCodeStatus::Active
                || $activation->expires_at->lte($now)
                || $activation->used_count >= $activation->max_uses
                || ($activation->intended_device_name !== null && $activation->intended_device_name !== $deviceName)) {
                if ($activation?->status === ActivationCodeStatus::Active && $activation->expires_at->lte($now)) {
                    $activation->update(['status' => ActivationCodeStatus::Expired]);
                }

                return null;
            }

            // Run device creation and token issuance in the same transaction so failed
            // provisioning cannot consume the code or leave a partial Device record.
            $provisioned = $onVerified($store, $activation);

            $nextCount = $activation->used_count + 1;
            $activation->update([
                'used_count' => $nextCount,
                'status' => $nextCount >= $activation->max_uses ? ActivationCodeStatus::Used : ActivationCodeStatus::Active,
            ]);

            $this->audit($activation, 'activation_code.used', null, $ipAddress, [
                'store_id' => $store->getKey(),
                'masked_suffix' => $activation->masked_suffix,
                'used_count' => $nextCount,
                'max_uses' => $activation->max_uses,
                'device_name' => $deviceName,
            ]);

            return $provisioned ?? $activation->fresh(['store.customer']);
        });
    }

    public function revoke(ActivationCode $activation, int $actorId, ?string $ipAddress): bool
    {
        return DB::transaction(function () use ($activation, $actorId, $ipAddress): bool {
            $locked = ActivationCode::query()->whereKey($activation->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== ActivationCodeStatus::Active) {
                return false;
            }
            if ($locked->expires_at->isPast()) {
                $locked->update(['status' => ActivationCodeStatus::Expired]);

                return false;
            }

            $locked->update(['status' => ActivationCodeStatus::Revoked]);
            $this->audit($locked, 'activation_code.revoked', $actorId, $ipAddress, [
                'masked_suffix' => $locked->masked_suffix,
                'used_count' => $locked->used_count,
            ]);

            return true;
        });
    }

    public function expirePastDue(): int
    {
        return ActivationCode::query()
            ->where('status', ActivationCodeStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => ActivationCodeStatus::Expired->value, 'updated_at' => now()]);
    }

    private function audit(ActivationCode $activation, string $action, ?int $actorId, ?string $ipAddress, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => ActivationCode::class,
            'resource_id' => (string) $activation->getKey(),
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'occurred_at' => now(),
        ]);
    }
}
