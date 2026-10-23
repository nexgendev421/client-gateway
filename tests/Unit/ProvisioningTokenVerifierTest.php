<?php

namespace Tests\Unit;

use App\Services\ProvisioningTokenVerificationException;
use App\Services\ProvisioningTokenVerifier;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProvisioningTokenVerifierTest extends TestCase
{
    private string $publicKeyPath;

    private string $privateKey;

    private string $opensslConfigPath;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'router-token-test-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);
        $this->opensslConfigPath = tempnam(sys_get_temp_dir(), 'router-openssl-');
        file_put_contents($this->opensslConfigPath, "openssl_conf = openssl_init\n[openssl_init]\nproviders = provider_sect\n[provider_sect]\ndefault = default_sect\n[default_sect]\nactivate = 1\n");

        [$this->privateKey, $publicKey] = $this->generateKeyPair('main');

        $this->publicKeyPath = tempnam(sys_get_temp_dir(), 'router-public-');
        file_put_contents($this->publicKeyPath, $publicKey);
        Config::set('provisioning.issuer', 'https://router.example.test');
        Config::set('provisioning.public_keys', ['test-key' => $this->publicKeyPath]);
        Config::set('provisioning.clock_skew_seconds', 0);
    }

    protected function tearDown(): void
    {
        if (isset($this->publicKeyPath) && is_file($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }

        if (isset($this->opensslConfigPath) && is_file($this->opensslConfigPath)) {
            unlink($this->opensslConfigPath);
        }

        if (isset($this->temporaryDirectory) && is_dir($this->temporaryDirectory)) {
            foreach (glob($this->temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_verifies_valid_rs256_provisioning_token(): void
    {
        $claims = $this->claims();
        $verified = app(ProvisioningTokenVerifier::class)->verify($this->sign($claims));

        $this->assertSame($claims, $verified);
    }

    public function test_rejects_expired_token(): void
    {
        $this->expectException(ProvisioningTokenVerificationException::class);

        app(ProvisioningTokenVerifier::class)->verify($this->sign($this->claims(['exp' => now()->subSecond()->timestamp])));
    }

    public function test_rejects_tampered_claims(): void
    {
        $token = $this->sign($this->claims());
        [$header, $payload, $signature] = explode('.', $token);
        $claims = json_decode($this->decode($payload), true, 16, JSON_THROW_ON_ERROR);
        $claims['device_id'] = 'SYP-TAMPERED';
        $tampered = $header.'.'.$this->encode(json_encode($claims, JSON_THROW_ON_ERROR)).'.'.$signature;

        $this->expectException(ProvisioningTokenVerificationException::class);
        app(ProvisioningTokenVerifier::class)->verify($tampered);
    }

    public function test_rejects_token_signed_by_wrong_key(): void
    {
        $token = $this->sign($this->claims());
        [, $wrongPublicKey] = $this->generateKeyPair('wrong');
        $wrongPath = tempnam(sys_get_temp_dir(), 'router-wrong-public-');
        file_put_contents($wrongPath, $wrongPublicKey);
        Config::set('provisioning.public_keys', ['test-key' => $wrongPath]);

        try {
            $this->expectException(ProvisioningTokenVerificationException::class);
            app(ProvisioningTokenVerifier::class)->verify($token);
        } finally {
            unlink($wrongPath);
        }
    }

    public function test_rejects_wrong_token_purpose(): void
    {
        $this->expectException(ProvisioningTokenVerificationException::class);

        app(ProvisioningTokenVerifier::class)->verify($this->sign($this->claims(['purpose' => 'licence_refresh'])));
    }

    private function claims(array $overrides = []): array
    {
        $now = now()->timestamp;

        return array_merge([
            'iss' => 'https://router.example.test',
            'aud' => 'customer-pos',
            'store_code' => 'ASIAHUD',
            'device_id' => 'SYP-TEST-0001',
            'iat' => $now,
            'exp' => $now + 300,
            'jti' => bin2hex(random_bytes(16)),
            'purpose' => 'device_provisioning',
        ], $overrides);
    }

    private function sign(array $claims): string
    {
        $header = $this->encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'test-key'], JSON_THROW_ON_ERROR));
        $payload = $this->encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signingInput = $header.'.'.$payload;
        $this->assertTrue(openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256));

        return $signingInput.'.'.$this->encode($signature);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    /** Generate test-only keys in a child PHP process so OpenSSL reads its config at process startup. */
    private function generateKeyPair(string $name): array
    {
        $privatePath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$name.'.private.pem';
        $publicPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$name.'.public.pem';
        $script = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'generate-key.php';
        file_put_contents($script, <<<'PHP'
<?php
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
if ($key === false || ! openssl_pkey_export($key, $private)) { exit(1); }
$details = openssl_pkey_get_details($key);
if (! is_array($details) || file_put_contents($argv[1], $private) === false || file_put_contents($argv[2], $details['key']) === false) { exit(2); }
PHP);
        $environment = getenv();
        $environment['OPENSSL_CONF'] = $this->opensslConfigPath;
        $process = proc_open([PHP_BINARY, $script, $privatePath, $publicPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        $this->assertIsResource($process, 'A PHP subprocess must be available for ephemeral RSA key generation.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $this->assertSame(0, $exitCode, trim($stderr.' '.$stdout));

        return [file_get_contents($privatePath), file_get_contents($publicPath)];
    }
}
