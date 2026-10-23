<?php

namespace App\Enums;

enum HealthCheckStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
}
