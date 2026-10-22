<?php

namespace App\Enums;

enum ActivationCodeStatus: string
{
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
