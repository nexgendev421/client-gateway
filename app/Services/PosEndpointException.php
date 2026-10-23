<?php

namespace App\Services;

use RuntimeException;

class PosEndpointException extends RuntimeException
{
    public function __construct(public readonly string $safeCode, string $safeMessage, public readonly ?int $responseTimeMs = null)
    {
        parent::__construct($safeMessage);
    }
}
