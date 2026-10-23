<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\LicenceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Licence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_licence_pages_require_authentication_and_roles_separate_view_from_management(): void
    {
        $licence = Licence::factory()->create();
        $this->get(route('admin.licences.index'))->assertRedirect(route('login'));
        $this->get(route('admin.licences.show', $licence))->assertRedirect(route('login'));

        $support = User::factory()->withRole(AdminRole::Support)->create();
        $this->actingAs($support)->get(route('admin.licences.index'))->assertOk();
        $this->get(route('admin.licences.show', $licence))->assertOk();
        $this->get(route('admin.licences.create'))->assertForbidden();
        $this->post(route('admin.licences.transition', [$licence, 'suspended']), ['reason' => 'Support restriction'])->assertForbidden();
    }

    public function test_super_admin_can_create_customer_licence_with_features_and_audit_it(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->get(route('admin.licences.create', ['customer_id' => $customer->id]))
            ->assertOk()->assertSee('Create customer licence')->assertSee($customer->business_name);

        $this->actingAs($admin)->post(route('admin.licences.store'), [
            'customer_id' => $customer->id,
            'plan' => 'Retail Plus',
            'plan_code' => 'RETAIL_PLUS',
            'status' => 'trial',
            'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
            'grace_until' => now()->addMonth()->addDays(7)->format('Y-m-d H:i:s'),
            'max_stores' => 3,
            'max_devices' => 12,
            'metadata_json' => '{"multi_store":true,"reports":["sales","stock"]}',
            'internal_notes' => 'Initial evaluation period',
        ])->assertSessionHasNoErrors();

        $licence = Licence::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('RETAIL_PLUS', $licence->plan_code);
        $this->assertSame(['multi_store' => true, 'reports' => ['sales', 'stock']], $licence->metadata);
        $this->assertSame(3, $licence->max_stores);
        $this->assertSame(12, $licence->max_devices);
        $this->assertSame('Initial evaluation period', $licence->internal_notes);
        $this->assertSame(LicenceStatus::Trial, $licence->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.created', 'resource_id' => (string) $licence->id]);
        $this->get(route('admin.licences.show', $licence))->assertOk()->assertSee('Feature entitlements')->assertSee('Retail Plus');
        $this->get(route('admin.licences.edit', $licence))->assertOk()->assertSee('Save changes');
    }

    public function test_customer_can_have_only_one_licence_and_feature_json_rejects_secrets(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $licence = Licence::factory()->create();
        $customerWithoutLicence = Customer::factory()->create();

        $this->actingAs($admin)->post(route('admin.licences.store'), [
            'customer_id' => $licence->customer_id,
            'plan' => 'Duplicate',
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('customer_id');

        $this->from(route('admin.licences.create'))->post(route('admin.licences.store'), [
            'customer_id' => $customerWithoutLicence->id,
            'plan' => 'Unsafe features',
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
            'metadata_json' => '{"api_token":"do-not-store"}',
        ])->assertSessionHasErrors('metadata_json');

        $this->assertDatabaseCount('licences', 1);
    }

    public function test_admin_can_update_plan_limits_features_and_notes_with_safe_change_audit(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $licence = Licence::factory()->create(['plan' => 'Basic', 'max_devices' => 5]);

        $this->actingAs($admin)->put(route('admin.licences.update', $licence), [
            'plan' => 'Growth',
            'plan_code' => 'GROWTH',
            'starts_at' => $licence->starts_at->format('Y-m-d H:i:s'),
            'valid_until' => $licence->valid_until->addDays(10)->format('Y-m-d H:i:s'),
            'grace_until' => '',
            'max_stores' => 5,
            'max_devices' => 20,
            'metadata_json' => '{"advanced_reports":true}',
            'internal_notes' => 'Updated by support contract',
        ])->assertSessionHasNoErrors();

        $licence->refresh();
        $this->assertSame('Growth', $licence->plan);
        $this->assertSame('GROWTH', $licence->plan_code);
        $this->assertSame(20, $licence->max_devices);
        $this->assertSame(['advanced_reports' => true], $licence->metadata);
        $audit = AuditLog::query()->where('action', 'licence.updated')->firstOrFail();
        $this->assertContains('plan', $audit->metadata['changed_fields']);
        $this->assertSame('Basic', $audit->metadata['before']['plan']);
        $this->assertSame('Growth', $audit->metadata['after']['plan']);
        $this->assertSame(['changed' => true], $audit->metadata['after']['metadata']);
    }

    public function test_expiry_extension_and_license_status_transitions_are_audited(): void
    {
        $admin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
        $licence = Licence::factory()->create([
            'status' => LicenceStatus::PastDue,
            'valid_until' => now()->addDays(5),
        ]);
        $this->actingAs($admin);

        $extendedTo = now()->addDays(30)->format('Y-m-d H:i:s');
        $this->post(route('admin.licences.extend', $licence), [
            'valid_until' => $extendedTo,
            'reason' => 'Customer renewal payment received',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($licence->fresh()->valid_until->gt(now()->addDays(29)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.extended', 'resource_id' => (string) $licence->id]);

        $this->post(route('admin.licences.transition', [$licence, 'grace']), [
            'grace_until' => now()->addDays(40)->format('Y-m-d H:i:s'),
            'reason' => 'Temporary payment grace',
        ])->assertSessionHasNoErrors();
        $this->assertSame(LicenceStatus::Grace, $licence->fresh()->status);

        $sensitiveReason = 'Payment settled with bearer token=sypd_'.str_repeat('a', 35).' and activation code 1234-5678';
        foreach ([
            'active' => LicenceStatus::Active,
            'restricted' => LicenceStatus::Restricted,
            'suspended' => LicenceStatus::Suspended,
            'cancelled' => LicenceStatus::Cancelled,
        ] as $state => $expected) {
            $reason = $state === 'active' ? $sensitiveReason : 'Status transition test';
            $this->post(route('admin.licences.transition', [$licence, $state]), ['reason' => $reason])
                ->assertSessionHasNoErrors();
            $this->assertSame($expected, $licence->fresh()->status);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.grace_configured', 'resource_id' => (string) $licence->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.restored_active', 'resource_id' => (string) $licence->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.restricted', 'resource_id' => (string) $licence->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.suspended', 'resource_id' => (string) $licence->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'licence.cancelled', 'resource_id' => (string) $licence->id]);
        $auditReason = json_encode(AuditLog::query()->where('action', 'licence.restored_active')->value('metadata'));
        $this->assertStringNotContainsString('sypd_', $auditReason);
        $this->assertStringNotContainsString('1234-5678', $auditReason);
    }
}
