<?php

namespace App\Services;

use RuntimeException;

class DeviceCredentialException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Device credential request could not be completed.');
    }
}
