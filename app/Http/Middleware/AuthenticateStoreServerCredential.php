<?php

namespace App\Http\Middleware;

use App\Services\StoreServerCredentialService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStoreServerCredential
{
    public function __construct(private readonly StoreServerCredentialService $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $credential = is_string($token) ? $this->credentials->authenticate($token) : null;
        if ($credential === null) {
            return new JsonResponse(['message' => 'POS server authentication failed.'], 401, [
                'WWW-Authenticate' => 'Bearer',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        $request->attributes->set('store_server_credential', $credential);
        $request->attributes->set('store', $credential->store);

        return $next($request);
    }
}
