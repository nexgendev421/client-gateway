<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceActivationRequest;
use App\Services\ActivationRejected;
use App\Services\DeviceActivationService;
use App\Services\ProvisioningTokenException;
use Illuminate\Http\JsonResponse;
use Throwable;

class ActivationController extends Controller
{
    public function activate(DeviceActivationRequest $request, DeviceActivationService $activation): JsonResponse
    {
        try {
            $result = $activation->activate($request->validated(), $request->ip());
        } catch (ActivationRejected) {
            return $this->rejected();
        } catch (ProvisioningTokenException) {
            return response()->json(['message' => 'Device activation is temporarily unavailable.'], 503);
        } catch (Throwable) {
            return response()->json(['message' => 'Device activation could not be completed. Please try again later.'], 503);
        }

        if ($result === null) {
            return $this->rejected();
        }

        return response()->json($result, 201)->header('Cache-Control', 'no-store, private');
    }

    private function rejected(): JsonResponse
    {
        return response()->json([
            'message' => 'Activation could not be completed. Check the details or contact support.',
            'code' => 'activation_failed',
        ], 422);
    }
}
