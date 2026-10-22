<?php

namespace App\Enums;

enum LicenceStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}
