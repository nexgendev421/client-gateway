<?php

namespace App\Policies;

use App\Models\ActivationCode;
use App\Models\User;

class ActivationCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-router');
    }

    public function view(User $user, ActivationCode $activationCode): bool
    {
        return $user->can('view-router');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-router');
    }

    public function revoke(User $user, ActivationCode $activationCode): bool
    {
        return $user->can('manage-router');
    }
}
