<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-router');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('view-router');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-router');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('manage-router');
    }

    public function changeStatus(User $user, Customer $customer): bool
    {
        return $user->can('manage-router');
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->can('manage-router');
    }
}
