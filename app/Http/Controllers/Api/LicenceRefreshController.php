<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\LicenceTokenIssuer;
use App\Services\ProvisioningTokenException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenceRefreshController extends Controller
{
    public function __invoke(Request $request, LicenceTokenIssuer $tokens): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->loadMissing('store.customer.licence');
        $licence = $device->store->customer->licence;

        if ($licence === null) {
            return response()->json([
                'state' => 'unlicensed',
                'signed_licence' => null,
                'issued_at' => now()->toIso8601String(),
                'valid_until' => null,
                'grace_until' => null,
            ])->header('Cache-Control', 'no-store, private');
        }

        try {
            // Device clients keep the existing customer-scoped document contract.
            // POS-server polling uses the separate Store-bound endpoint.
            $signed = $tokens->issue($licence);
        } catch (ProvisioningTokenException) {
            return response()->json(['message' => 'Licence refresh is temporarily unavailable.'], 503);
        }

        return response()->json([
            'state' => $licence->status->value,
            'signed_licence' => $signed['token'],
            'issued_at' => $signed['issued_at'],
            'valid_until' => $licence->valid_until->toIso8601String(),
            'grace_until' => $licence->grace_until?->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
