<?php

return [
    'issuer' => env('ROUTER_PROVISIONING_ISSUER', env('APP_URL', 'https://router.siyazpos.com')),
    'key_id' => env('ROUTER_PROVISIONING_KEY_ID', 'router-provisioning-v1'),
    'private_key_path' => env('ROUTER_PROVISIONING_PRIVATE_KEY_PATH'),
    'private_key_passphrase' => env('ROUTER_PROVISIONING_PRIVATE_KEY_PASSPHRASE'),
    'ttl_seconds' => 300,
];
