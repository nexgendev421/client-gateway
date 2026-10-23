<?php

return [
    'health_checks' => [
        'retention_days' => (int) env('ROUTER_HEALTH_RETENTION_DAYS', 30),
    ],
];
