<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class PosLicenceKeysController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $keys = [];
        $configured = (array) config('provisioning.public_keys', []);
        $privatePath = config('provisioning.private_key_path');
        $privatePem = is_string($privatePath) && $privatePath !== '' ? @file_get_contents($privatePath) : false;
        $private = is_string($privatePem) ? @openssl_pkey_get_private($privatePem, (string) config('provisioning.private_key_passphrase', '')) : false;
        $activeDetails = $private ? openssl_pkey_get_details($private) : false;
        if (is_array($activeDetails) && ($activeDetails['type'] ?? null) === OPENSSL_KEYTYPE_RSA && ($activeDetails['bits'] ?? 0) >= 2048) {
            $configured[(string) config('provisioning.key_id')] = $activeDetails['key'];
        }
        foreach ($configured as $kid => $value) {
            if (! is_string($kid) || ! preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $kid) || ! is_string($value)) {
                continue;
            }
            $pem = str_contains($value, '-----BEGIN PUBLIC KEY-----')
                ? $value
                : (is_file($value) && is_readable($value) ? @file_get_contents($value) : false);
            $key = is_string($pem) ? @openssl_pkey_get_public($pem) : false;
            $details = $key ? openssl_pkey_get_details($key) : false;
            if (is_array($details) && ($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA && ($details['bits'] ?? 0) >= 2048) {
                $keys[] = ['kid' => $kid, 'alg' => 'RS256', 'pem' => $details['key']];
            }
        }

        return response()->json(['issuer' => rtrim((string) config('provisioning.issuer'), '/'), 'keys' => $keys])
            ->header('Cache-Control', 'public, max-age=300')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
