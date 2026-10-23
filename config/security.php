<?php

return [
    // Production should retain the default requirement. Local development can
    // opt out explicitly in .env without weakening deployed configurations.
    'require_super_admin_mfa' => env('ROUTER_REQUIRE_SUPER_ADMIN_MFA', true),
];
