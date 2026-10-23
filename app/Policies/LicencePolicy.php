<?php

namespace App\Policies;

use App\Models\Licence;
use App\Models\User;

class LicencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-router');
    }

    public function view(User $user, Licence $licence): bool
    {
        return $user->can('view-router');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-router');
    }

    public function update(User $user, Licence $licence): bool
    {
        return $user->can('manage-router');
    }

    public function changeStatus(User $user, Licence $licence): bool
    {
        return $user->can('manage-router');
    }
}
