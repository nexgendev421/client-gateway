<?php

namespace Tests\Unit;

use App\Enums\LicenceStatus;
use App\Models\Device;
use App\Models\Licence;
use App\Models\Store;
use App\Services\LicenceTokenIssuer;
use App\Services\ProvisioningTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LicenceTokenIssuerTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'router-licence-test-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);
        $configPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'openssl.cnf';
        file_put_contents($configPath, "openssl_conf = openssl_init\n[openssl_init]\nproviders = provider_sect\n[provider_sect]\ndefault = default_sect\n[default_sect]\nactivate = 1\n");
        $generatorPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'generate-key.php';
        $privatePath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'private.pem';
        $publicPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'public.pem';
        file_put_contents($generatorPath, <<<'PHP'
<?php
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
if ($key === false || ! openssl_pkey_export($key, $private)) { exit(1); }
$details = openssl_pkey_get_details($key);
if (! is_array($details) || file_put_contents($argv[1], $private) === false || file_put_contents($argv[2], $details['key']) === false) { exit(2); }
PHP);
        $environment = getenv();
        $environment['OPENSSL_CONF'] = $configPath;
        $process = proc_open([PHP_BINARY, $generatorPath, $privatePath, $publicPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $this->assertSame(0, $exitCode, trim($stdout.' '.$stderr));

        $this->privateKey = file_get_contents($privatePath);
        $this->publicKey = file_get_contents($publicPath);
        Config::set('provisioning.private_key_pem', $this->privateKey);
        Config::set('provisioning.private_key_passphrase', '');
        Config::set('provisioning.key_id', 'router-test-key-2');
        Config::set('provisioning.issuer', 'https://router.example.test');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_licence_document_is_rs256_signed_and_contains_required_offline_claims_only(): void
    {
        $licence = Licence::factory()->create([
            'plan' => 'Retail Plus',
            'plan_code' => 'RETAIL_PLUS',
            'status' => LicenceStatus::Grace,
            'valid_until' => now()->addDays(3),
            'grace_until' => now()->addDays(10),
            'max_stores' => 4,
            'max_devices' => 25,
            'metadata' => ['multi_store' => true, 'reports' => ['sales', 'stock'], 'api_token' => 'sypd_'.str_repeat('x', 35)],
            'internal_notes' => 'Private internal note; must never reach the token.',
        ]);

        $issued = app(LicenceTokenIssuer::class)->issue($licence);
        [$headerPart, $claimsPart, $signaturePart] = explode('.', $issued['token']);
        $header = json_decode($this->decode($headerPart), true, 16, JSON_THROW_ON_ERROR);
        $claims = json_decode($this->decode($claimsPart), true, 32, JSON_THROW_ON_ERROR);
        $publicKey = openssl_pkey_get_public($this->publicKey);

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('router-test-key-2', $header['kid']);
        $this->assertSame(1, openssl_verify($headerPart.'.'.$claimsPart, $this->decode($signaturePart), $publicKey, OPENSSL_ALGO_SHA256));
        $this->assertSame('https://router.example.test', $claims['iss']);
        $this->assertSame('customer-pos', $claims['aud']);
        $this->assertSame((string) $licence->customer_id, $claims['customer_id']);
        $this->assertSame((string) $licence->id, $claims['licence_id']);
        $this->assertSame('RETAIL_PLUS', $claims['plan_code']);
        $this->assertSame('grace', $claims['status']);
        $this->assertSame(4, $claims['max_stores']);
        $this->assertSame(25, $claims['max_devices']);
        $this->assertSame(['multi_store' => true, 'reports' => ['sales', 'stock']], $claims['features']);
        $this->assertSame('signed_licence', $claims['purpose']);
        $this->assertNotEmpty($claims['jti']);
        $this->assertArrayNotHasKey('exp', $claims);
        $this->assertArrayNotHasKey('internal_notes', $claims);
        $this->assertStringNotContainsString('Private internal note', $issued['token']);
    }

    public function test_tampering_with_licence_claims_invalidates_the_signature(): void
    {
        $issued = app(LicenceTokenIssuer::class)->issue(Licence::factory()->create());
        [$headerPart, $claimsPart, $signaturePart] = explode('.', $issued['token']);
        $claims = json_decode($this->decode($claimsPart), true, 32, JSON_THROW_ON_ERROR);
        $claims['max_devices'] = 999999;
        $tamperedClaims = $this->encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $publicKey = openssl_pkey_get_public($this->publicKey);

        $this->assertSame(0, openssl_verify($headerPart.'.'.$tamperedClaims, $this->decode($signaturePart), $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function test_expired_licence_still_produces_a_verifiable_document_with_signed_expiry_state(): void
    {
        $licence = Licence::factory()->create([
            'status' => LicenceStatus::Restricted,
            'valid_until' => now()->subDays(2),
            'grace_until' => null,
        ]);
        $issued = app(LicenceTokenIssuer::class)->issue($licence);
        [, $claimsPart, $signaturePart] = explode('.', $issued['token']);
        $claims = json_decode($this->decode($claimsPart), true, 32, JSON_THROW_ON_ERROR);
        $publicKey = openssl_pkey_get_public($this->publicKey);

        $this->assertSame('restricted', $claims['status']);
        $this->assertLessThan(now()->timestamp, $claims['valid_until']);
        $this->assertNull($claims['grace_until']);
        $this->assertSame(1, openssl_verify(explode('.', $issued['token'])[0].'.'.$claimsPart, $this->decode($signaturePart), $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function test_pos_server_licence_document_is_bound_to_one_store_and_has_offline_deadlines(): void
    {
        $licence = Licence::factory()->create();
        $store = Store::factory()->for($licence->customer)->create(['store_code' => 'greenhd01']);
        $issued = app(LicenceTokenIssuer::class)->issue($licence, $store);
        [, $claimsPart] = explode('.', $issued['token']);
        $claims = json_decode($this->decode($claimsPart), true, 32, JSON_THROW_ON_ERROR);

        $this->assertSame((string) $store->id, $claims['store_id']);
        $this->assertSame('GREENHD01', $claims['store_code']);
        $this->assertGreaterThan($claims['iat'], $claims['refresh_after']);
        $this->assertGreaterThan($claims['refresh_after'], $claims['offline_until']);
        $this->assertLessThanOrEqual(72 * 3600 + 5, $claims['offline_until'] - $claims['iat']);
    }

    public function test_provisioning_tokens_continue_to_use_the_shared_signer(): void
    {
        $store = Store::factory()->create();
        $device = Device::factory()->for($store)->create();
        $issued = app(ProvisioningTokenIssuer::class)->issue($store, $device);
        [$headerPart, $claimsPart, $signaturePart] = explode('.', $issued['token']);
        $publicKey = openssl_pkey_get_public($this->publicKey);

        $this->assertSame(1, openssl_verify($headerPart.'.'.$claimsPart, $this->decode($signaturePart), $publicKey, OPENSSL_ALGO_SHA256));
        $this->assertSame('device_provisioning', json_decode($this->decode($claimsPart), true)['purpose']);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
