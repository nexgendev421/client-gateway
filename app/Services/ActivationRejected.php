<?php

namespace App\Services;

use RuntimeException;

class ActivationRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Activation could not be completed. Check the details or contact support.');
    }
}
