<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceCredential;
use App\Services\DeviceCredentialException;
use App\Services\DeviceCredentialService;
use App\Services\DeviceProvisioningExchangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DeviceCredentialController extends Controller
{
    public function exchange(Request $request, DeviceProvisioningExchangeService $exchange): JsonResponse
    {
        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return $this->unauthorized();
        }

        try {
            $issued = $exchange->exchange($token, $request->ip());
        } catch (DeviceCredentialException) {
            return $this->unauthorized();
        } catch (Throwable) {
            return response()->json(['message' => 'Device credential service is temporarily unavailable.'], 503);
        }

        return response()->json($issued, 201)->header('Cache-Control', 'no-store, private');
    }

    public function rotate(Request $request, DeviceCredentialService $credentials): JsonResponse
    {
        /** @var DeviceCredential $current */
        $current = $request->attributes->get('device_credential');

        try {
            $issued = $credentials->issue($current->device, null, $request->ip(), rotate: true);
        } catch (DeviceCredentialException) {
            return $this->unauthorized();
        }

        return response()->json([
            'device_reference' => $current->device->public_id,
            'credential' => $issued['token'],
            'expires_at' => null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request, DeviceCredentialService $credentials): JsonResponse
    {
        /** @var DeviceCredential $current */
        $current = $request->attributes->get('device_credential');
        $credentials->revoke($current, null, $request->ip());

        return response()->json([], 204)->header('Cache-Control', 'no-store, private');
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Device authentication failed.'], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
