<?php

namespace App\Services;

use RuntimeException;

class HealthCheckInProgress extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A health check is already running for this store.');
    }
}
