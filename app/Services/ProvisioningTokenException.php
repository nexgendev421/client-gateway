<?php

namespace App\Services;

use RuntimeException;

class ProvisioningTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Asymmetric token signing is unavailable.');
    }
}
