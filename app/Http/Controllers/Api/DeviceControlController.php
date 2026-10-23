<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceHeartbeatRequest;
use App\Models\Device;
use App\Services\DeviceConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceControlController extends Controller
{
    public function heartbeat(DeviceHeartbeatRequest $request): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->attributes->get('device');
        $data = $request->validated();

        if ($authenticatedDevice->platform->value !== $data['platform']) {
            return response()->json(['message' => 'Device telemetry is invalid.'], 422);
        }

        $updatedAt = DB::transaction(function () use ($authenticatedDevice, $data, $request): ?string {
            $device = Device::query()->lockForUpdate()->find($authenticatedDevice->getKey());
            if ($device === null || $device->status !== DeviceStatus::Active) {
                return null;
            }

            $device->update([
                'app_version' => $data['app_version'],
                'os_version' => $data['os_version'] ?? null,
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
            ]);

            return $device->last_seen_at?->toIso8601String();
        });

        if ($updatedAt === null) {
            return response()->json(['message' => 'Device authentication failed.'], 401);
        }

        return response()->json(['status' => 'ok', 'last_seen_at' => $updatedAt])->header('Cache-Control', 'no-store, private');
    }

    public function config(Request $request, DeviceConfigurationService $configuration): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        return response()->json($configuration->forDevice($device))->header('Cache-Control', 'no-store, private');
    }
}
