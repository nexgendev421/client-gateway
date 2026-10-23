<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-router');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can('view-router');
    }

    public function manage(User $user, Device $device): bool
    {
        return $user->can('manage-router');
    }
}
