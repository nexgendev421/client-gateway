<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\LicenceStatus;
use App\Models\Licence;
use App\Models\Store;
use App\Models\StoreServerCredential;
use App\Models\User;
use App\Services\LicenceTokenIssuer;
use App\Services\StoreServerCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PosLicenceRefreshApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_store_server_credentials_can_fetch_licence(): void
    {
        $this->getJson(route('api.v1.pos.licence.refresh'))->assertUnauthorized();
        $this->getJson(route('api.v1.pos.licence.refresh'), ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
    }

    public function test_server_credential_receives_store_bound_signed_licence(): void
    {
        $store = Store::factory()->create();
        $licence = Licence::factory()->for($store->customer)->create(['status' => LicenceStatus::Active]);
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $issued = app(StoreServerCredentialService::class)->issue($store, $admin, '127.0.0.1');
        $credential = StoreServerCredential::query()->firstOrFail();
        $this->assertNotSame($issued['token'], $credential->token_hash);
        $this->assertTrue(Hash::check($issued['token'], $credential->token_hash));

        $issuer = Mockery::mock(LicenceTokenIssuer::class);
        $issuer->shouldReceive('issue')->once()->with(
            Mockery::on(fn (Licence $candidate): bool => $candidate->is($licence)),
            Mockery::on(fn (Store $candidate): bool => $candidate->is($store)),
        )->andReturn(['token' => 'signed.store.licence', 'issued_at' => now()->toIso8601String()]);
        $this->app->instance(LicenceTokenIssuer::class, $issuer);

        $response = $this->getJson(route('api.v1.pos.licence.refresh'), ['Authorization' => 'Bearer '.$issued['token']])
            ->assertOk()->assertJsonPath('state', 'active')->assertJsonPath('signed_licence', 'signed.store.licence')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('store_server_credentials', ['id' => $credential->id]);
        $this->assertNotNull($credential->fresh()->last_used_at);
        $this->assertStringNotContainsString($issued['token'], $response->getContent());
    }

    public function test_unlicensed_store_receives_explicit_unlicensed_state(): void
    {
        $store = Store::factory()->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $issued = app(StoreServerCredentialService::class)->issue($store, $admin, null);
        $response = $this->getJson(route('api.v1.pos.licence.refresh'), ['Authorization' => 'Bearer '.$issued['token']])
            ->assertOk()->assertJson(['state' => 'unlicensed', 'signed_licence' => null])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['state', 'signed_licence', 'issued_at'], array_keys($response->json()));
    }

    public function test_rotating_store_credential_revokes_old_secret(): void
    {
        $store = Store::factory()->create();
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $first = app(StoreServerCredentialService::class)->issue($store, $admin, null);
        $second = app(StoreServerCredentialService::class)->issue($store, $admin, null, true);
        $this->assertNull(app(StoreServerCredentialService::class)->authenticate($first['token']));
        $this->assertNotNull(app(StoreServerCredentialService::class)->authenticate($second['token']));
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.licence_credential_rotated']);
    }
}
