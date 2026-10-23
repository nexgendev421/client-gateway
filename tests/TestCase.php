<?php

namespace Tests;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $result = parent::actingAs($user, $guard);
        if ($user instanceof User && $user->role === AdminRole::SuperAdmin && $user->hasMfaEnabled()) {
            $this->withSession(['mfa_verified_user_id' => $user->getKey()]);
        }

        return $result;
    }
}
