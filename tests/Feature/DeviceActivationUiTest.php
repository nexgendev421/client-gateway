<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeviceActivationUiTest extends TestCase
{
    public function test_activation_screen_is_public_touch_friendly_and_not_cached(): void
    {
        $this->get(route('device.activation.start'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('viewport-fit=cover')
            ->assertSee('Store code')
            ->assertSee('Activation code')
            ->assertSee('Connect Store')
            ->assertSee('device-id')
            ->assertSee('hasNativeBridge')
            ->assertSee('Open this activation page inside the SiyazPOS app.')
            ->assertSee('siyazpos-activation-completed')
            ->assertDontSee('provisioning_token');
    }

    public function test_wrapper_metadata_is_validated_and_used_to_prefill_the_screen(): void
    {
        $this->get(route('device.activation.start', [
            'device_id' => 'SYP-WINDOWS-0001',
            'device_name' => 'Front Till',
            'platform' => 'windows',
            'app_version' => '1.8.2',
        ]))->assertOk()
            ->assertSee('Front Till')
            ->assertSee('windows')
            ->assertSee('1.8.2')
            ->assertSee('SYP-WINDOWS-0001');
    }

    public function test_android_user_agent_is_detected_and_untrusted_query_values_are_sanitized(): void
    {
        $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) WebView'])
            ->get(route('device.activation.start', [
                'device_id' => ['invalid'],
                'platform' => 'ios',
                'device_name' => '<script>alert(1)</script>',
                'app_version' => '<bad>',
            ]));

        $response->assertOk()
            ->assertSee('android')
            ->assertSee('0.0.0')
            ->assertDontSee('<script>')
            ->assertDontSee('alert(1)');
    }
}
