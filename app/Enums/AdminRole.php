<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Support = 'support';
    case ReadOnly = 'read_only';
}
