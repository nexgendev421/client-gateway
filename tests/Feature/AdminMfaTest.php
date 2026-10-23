<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\MfaRecoveryCode;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc6238_sha1_six_digit_vector(): void
    {
        $this->assertSame(
            '287082',
            app(TotpService::class)->codeAt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59),
        );
    }

    public function test_super_admin_without_mfa_is_forced_to_enrol_after_login(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $admin->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null])->save();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.setup'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertRedirect(route('admin.mfa.setup'));
    }

    public function test_enabled_admin_must_complete_totp_challenge_before_access(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_enabled_at' => now()])->save();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();
        $this->get(route('mfa.challenge'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('mfa.challenge.verify'), ['code' => '111111'])->assertSessionHasErrors('code');

        $code = app(TotpService::class)->codeAt('JBSWY3DPEHPK3PXP');
        $this->post(route('mfa.challenge.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_recovery_code_is_hashed_and_can_only_be_used_once(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_enabled_at' => now()])->save();
        $plain = 'ABCD-EFGH-JKLM';
        $stored = MfaRecoveryCode::query()->create([
            'user_id' => $admin->id,
            'code_hash' => Hash::make('ABCDEFGHJKLM'),
        ]);

        $this->withSession(['mfa_pending_user_id' => $admin->id, 'mfa_pending_at' => now()->timestamp]);
        $this->post(route('mfa.challenge.verify'), ['code' => $plain])->assertRedirect(route('dashboard'));
        $this->assertNotSame($plain, $stored->fresh()->code_hash);
        $this->assertNotNull($stored->fresh()->used_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.mfa_recovery_code_used']);
    }

    public function test_mfa_challenge_attempts_are_rate_limited(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_enabled_at' => now()])->save();
        $this->withSession(['mfa_pending_user_id' => $admin->id, 'mfa_pending_at' => now()->timestamp]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('mfa.challenge.verify'), ['code' => '111111'])->assertRedirect();
        }

        $this->post(route('mfa.challenge.verify'), ['code' => '111111'])->assertTooManyRequests();
    }

    public function test_totp_step_cannot_be_replayed_for_a_second_sign_in(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_enabled_at' => now()])->save();
        $this->withSession(['mfa_pending_user_id' => $admin->id, 'mfa_pending_at' => now()->timestamp]);
        $code = app(TotpService::class)->codeAt('JBSWY3DPEHPK3PXP');

        $this->post(route('mfa.challenge.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->withSession(['mfa_pending_user_id' => $admin->id, 'mfa_pending_at' => now()->timestamp]);
        $this->post(route('mfa.challenge.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_expired_challenge_is_rejected_and_cancel_clears_pending_identity(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP', 'mfa_enabled_at' => now()])->save();
        $this->withSession([
            'mfa_pending_user_id' => $admin->id,
            'mfa_pending_at' => now()->subMinutes(11)->timestamp,
        ]);

        $this->get(route('mfa.challenge'))->assertRedirect(route('login'));
        $this->withSession(['mfa_pending_user_id' => $admin->id, 'mfa_pending_at' => now()->timestamp]);
        $response = $this->post(route('mfa.challenge.cancel'));
        $response->assertRedirect(route('login'))->assertSessionMissing('mfa_pending_user_id');
        $this->assertGuest();
    }

    public function test_mfa_setup_encrypts_secret_and_only_shows_recovery_codes_once(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $this->post(route('admin.mfa.setup.begin'))->assertRedirect(route('admin.mfa.setup'));
        $pending = $admin->fresh();
        $encryptedPending = $pending->getRawOriginal('mfa_pending_secret');
        $this->assertNotSame($pending->mfa_pending_secret, $encryptedPending);
        $this->get(route('admin.mfa.setup'))->assertOk()->assertSee('data:image/svg+xml');

        $code = app(TotpService::class)->codeAt($pending->mfa_pending_secret);
        $response = $this->post(route('admin.mfa.setup.confirm'), ['code' => $code]);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $response->assertViewHas('recoveryCodes', fn (array $codes): bool => count($codes) === 10);
        $this->assertTrue($admin->fresh()->hasMfaEnabled());
        $this->assertNotSame($admin->fresh()->mfa_secret, $admin->fresh()->getRawOriginal('mfa_secret'));
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
    }

    public function test_mfa_settings_require_authentication_and_super_admin_cannot_disable_mfa(): void
    {
        $this->get(route('admin.mfa.setup'))->assertRedirect(route('login'));

        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin)->post(route('admin.mfa.disable'), [
            'password' => 'password',
            'code' => app(TotpService::class)->codeAt($admin->mfa_secret),
        ])->assertForbidden();
    }
}
