<?php

namespace App\Http\Middleware;

use App\Services\DeviceCredentialService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceCredential
{
    public function __construct(private readonly DeviceCredentialService $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $credential = is_string($token) ? $this->credentials->authenticate($token) : null;
        if ($credential === null) {
            return new JsonResponse(['message' => 'Device authentication failed.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        $request->attributes->set('device_credential', $credential);
        $request->attributes->set('device', $credential->device);

        return $next($request);
    }
}
