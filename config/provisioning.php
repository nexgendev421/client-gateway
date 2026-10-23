<?php

return [
    'issuer' => env('ROUTER_PROVISIONING_ISSUER', env('APP_URL', 'https://router.siyazpos.com')),
    'key_id' => env('ROUTER_PROVISIONING_KEY_ID', 'router-provisioning-v1'),
    'private_key_path' => env('ROUTER_PROVISIONING_PRIVATE_KEY_PATH'),
    'private_key_passphrase' => env('ROUTER_PROVISIONING_PRIVATE_KEY_PASSPHRASE'),
    // JSON map of key IDs to public PEM file paths. Retain prior public keys
    // here while short-lived tokens signed by a rotated key are still valid.
    'public_keys' => json_decode((string) env('ROUTER_PROVISIONING_PUBLIC_KEYS', '{}'), true) ?: [],
    'clock_skew_seconds' => 30,
    'ttl_seconds' => 300,
];
