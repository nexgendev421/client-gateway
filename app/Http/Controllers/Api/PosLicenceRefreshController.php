<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Licence;
use App\Models\Store;
use App\Services\LicenceTokenIssuer;
use App\Services\ProvisioningTokenException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosLicenceRefreshController extends Controller
{
    public function __invoke(Request $request, LicenceTokenIssuer $tokens): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $licence = $store->customer->licence;
        if (! $licence instanceof Licence) {
            return response()->json([
                'state' => 'unlicensed',
                'store_code' => strtoupper($store->store_code),
                'signed_licence' => null,
                'issued_at' => now()->toIso8601String(),
            ])->header('Cache-Control', 'no-store, private');
        }

        try {
            $signed = $tokens->issue($licence, $store);
        } catch (ProvisioningTokenException) {
            return response()->json(['message' => 'Licence refresh is temporarily unavailable.'], 503)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json([
            'state' => $licence->status->value,
            'store_code' => strtoupper($store->store_code),
            'signed_licence' => $signed['token'],
            'issued_at' => $signed['issued_at'],
            'valid_until' => $licence->valid_until->toIso8601String(),
            'grace_until' => $licence->grace_until?->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
