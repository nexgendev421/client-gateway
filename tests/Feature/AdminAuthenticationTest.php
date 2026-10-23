<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sign_in_with_password_hashing_and_no_remember_me(): void
    {
        $admin = User::factory()->create(['password' => 'correct-password', 'remember_token' => null]);

        $this->assertNotSame('correct-password', $admin->fresh()->getRawOriginal('password'));

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull($admin->fresh()->remember_token);
    }

    public function test_incorrect_password_is_rejected_with_a_generic_message(): void
    {
        $admin = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'incorrect-password',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_disabled_admin_cannot_sign_in(): void
    {
        $admin = User::factory()->disabled()->create();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(AdminStatus::Disabled, $admin->fresh()->status);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_ends_the_session(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_bypasses_gates_while_other_roles_use_defined_abilities(): void
    {
        $superAdmin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $support = User::factory()->withRole(AdminRole::Support)->create();
        $readOnly = User::factory()->withRole(AdminRole::ReadOnly)->create();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('future.permission'));
        $this->assertTrue(Gate::forUser($support)->allows('view-router'));
        $this->assertFalse(Gate::forUser($support)->allows('manage-router'));
        $this->assertTrue(Gate::forUser($readOnly)->allows('view-router'));
        $this->assertFalse(Gate::forUser($readOnly)->allows('manage-router'));
    }

    public function test_disabled_admin_is_denied_even_when_role_is_super_admin(): void
    {
        $admin = User::factory()->disabled()->withRole(AdminRole::SuperAdmin)->create();

        $this->assertFalse(Gate::forUser($admin)->allows('future.permission'));
    }

    public function test_an_admin_disabled_during_a_session_is_signed_out(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $admin->update(['status' => AdminStatus::Disabled]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->from(route('login'))->post(route('login.store'), [
                'email' => 'unknown@example.test',
                'password' => 'invalid-password',
            ])->assertRedirect(route('login'));
        }

        $this->post(route('login.store'), [
            'email' => 'unknown@example.test',
            'password' => 'invalid-password',
        ])->assertTooManyRequests();
    }

    public function test_unexpected_errors_do_not_expose_exception_details(): void
    {
        Route::get('/_test/unexpected-error', fn () => throw new \RuntimeException('Database password leaked'));

        $this->get('/_test/unexpected-error')
            ->assertServerError()
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Database password leaked')
            ->assertDontSee('Stack trace');
    }
}
