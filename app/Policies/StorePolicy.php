<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-router');
    }

    public function view(User $user, Store $store): bool
    {
        return $user->can('view-router');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-operations');
    }

    public function update(User $user, Store $store): bool
    {
        return $user->can('manage-operations');
    }

    public function changeStatus(User $user, Store $store): bool
    {
        return $user->can('manage-operations');
    }

    public function testConnection(User $user, Store $store): bool
    {
        return $user->can('manage-operations');
    }

    public function generateActivationCode(User $user, Store $store): bool
    {
        return $user->can('manage-operations');
    }
}
