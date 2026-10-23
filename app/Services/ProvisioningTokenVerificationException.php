<?php

namespace App\Services;

use RuntimeException;

class ProvisioningTokenVerificationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Provisioning token is invalid or expired.');
    }
}
