<?php

namespace App\Enums;

enum HealthCheckStatus: string
{
    case Healthy = 'healthy';
    case Unhealthy = 'unhealthy';
    case Unreachable = 'unreachable';
}
