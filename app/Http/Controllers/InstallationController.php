<?php

namespace App\Http\Controllers;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InstallationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        abort_if($this->isInstalled(), 404);

        return view('install.create');
    }

    public function store(Request $request): Response
    {
        abort_if($this->isInstalled(), 404);

        $validator = Validator::make($request->all(), [
            'app_url' => ['required', 'string', 'max:255', 'url', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = is_string($value) ? parse_url($value) : false;
                $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
                $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
                if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                    || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)
                    || (($parts['scheme'] ?? '') !== 'https' && ! ($local && ($parts['scheme'] ?? '') === 'http'))) {
                    $fail('Use the public HTTPS address for this Router. HTTP is allowed only for local development.');
                }
            }],
            'db_host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9.-]+$/'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'db_username' => ['required', 'string', 'max:128', 'not_regex:/[\r\n\x00]/'],
            'db_password' => ['required', 'string', 'max:1024', 'not_regex:/[\r\n\x00]/'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']));
        }

        $data = $validator->validated();
        $parts = parse_url($data['app_url']);
        $local = in_array(strtolower((string) ($parts['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true);
        if (! $request->isSecure() && ! $local) {
            return back()->withErrors(['app_url' => 'Open the installer over HTTPS before submitting administrator or database credentials.'])
                ->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']));
        }

        $lock = $this->acquireInstallLock();
        if ($lock === false) {
            return back()->withErrors(['install' => 'Another installation attempt is in progress. Wait a moment and reload this page.']);
        }

        try {
            $this->install($data);

            return response()->view('install.complete');
        } catch (Throwable) {
            return back()->withErrors(['install' => 'Installation could not be completed. Check the database details and server PHP/OpenSSL permissions, then try again. No secret values were recorded.'])
                ->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string, mixed> $data */
    private function install(array $data): void
    {
        $pdo = $this->connectToDatabase($data);
        $this->ensureInstallableDatabase($pdo);

        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $appUrl = rtrim($data['app_url'], '/');
        $keyId = 'router-'.bin2hex(random_bytes(6));
        $keyDirectory = storage_path('app/private/provisioning');
        if (! is_dir($keyDirectory) && ! mkdir($keyDirectory, 0700, true) && ! is_dir($keyDirectory)) {
            throw new \RuntimeException;
        }
        @chmod($keyDirectory, 0700);

        $privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if ($privateKey === false || ! openssl_pkey_export($privateKey, $privatePem)) {
            throw new \RuntimeException;
        }
        $publicDetails = openssl_pkey_get_details($privateKey);
        if (! is_array($publicDetails) || ! isset($publicDetails['key'])) {
            throw new \RuntimeException;
        }

        $privatePath = $keyDirectory.DIRECTORY_SEPARATOR.'provisioning-private.pem';
        $publicPath = $keyDirectory.DIRECTORY_SEPARATOR.'provisioning-public.pem';
        $this->writePrivateFile($privatePath, $privatePem);
        $this->writePrivateFile($publicPath, $publicDetails['key']);

        $environment = [
            'APP_NAME' => 'SiyazPOS Router',
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'ROUTER_REQUIRE_SUPER_ADMIN_MFA' => 'true',
            'ROUTER_PROVISIONING_ISSUER' => $appUrl,
            'ROUTER_PROVISIONING_KEY_ID' => $keyId,
            'ROUTER_PROVISIONING_PRIVATE_KEY_PATH' => $privatePath,
            'ROUTER_PROVISIONING_PRIVATE_KEY_PASSPHRASE' => '',
            'ROUTER_PROVISIONING_PUBLIC_KEYS' => json_encode([$keyId => $publicPath], JSON_THROW_ON_ERROR),
            'ROUTER_HEALTH_RETENTION_DAYS' => '30',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'],
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => '120',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'daily',
            'LOG_LEVEL' => 'warning',
        ];

        $envPath = base_path('.env');
        $existingEnv = is_file($envPath) ? (string) file_get_contents($envPath) : '';
        $installingPath = storage_path('app/router-installing');
        if (preg_match('/^APP_KEY\s*=\s*\S+/m', $existingEnv) === 1 && ! is_file($installingPath)) {
            throw new \RuntimeException;
        }
        $this->writePrivateFile($installingPath, "installation in progress\n");
        $envContents = "# Generated by the SiyazPOS Router installer. Protect this file.\n";
        foreach ($environment as $name => $value) {
            $envContents .= $name.'='.$this->quoteEnvValue((string) $value)."\n";
        }
        $this->writePrivateFile($envPath, $envContents);

        config([
            'app.key' => $appKey,
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => $appUrl,
            'security.require_super_admin_mfa' => true,
            'provisioning.issuer' => $appUrl,
            'provisioning.key_id' => $keyId,
            'provisioning.private_key_path' => $privatePath,
            'provisioning.public_keys' => [$keyId => $publicPath],
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'database.default' => 'mysql',
            'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => (int) $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'],
        ]);
        app()->forgetInstance('encrypter');
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');

        if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new \RuntimeException;
        }

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        DB::transaction(function () use ($data): void {
            User::query()->create([
                'name' => $data['admin_name'],
                'email' => mb_strtolower($data['admin_email']),
                'password' => $data['admin_password'],
                'role' => AdminRole::SuperAdmin,
                'status' => AdminStatus::Active,
            ]);
        });

        $installedPath = storage_path('app/router-installed');
        $this->writePrivateFile($installedPath, now()->toIso8601String()."\n");
        @unlink($installingPath);
        @unlink(storage_path('app/installer-session.key'));
    }

    /** @param array<string, mixed> $data */
    private function connectToDatabase(array $data): PDO
    {
        if (! extension_loaded('pdo_mysql')) {
            throw new \RuntimeException;
        }

        return new PDO(
            'mysql:host='.$data['db_host'].';port='.$data['db_port'].';dbname='.$data['db_database'].';charset=utf8mb4',
            $data['db_username'],
            $data['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8],
        );
    }

    private function ensureInstallableDatabase(PDO $pdo): void
    {
        $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $migrations = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn();
        $users = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn();
        if (($tables > 0 && $migrations === 0) || $users > 0) {
            throw new \RuntimeException;
        }
    }

    private function isInstalled(): bool
    {
        if (is_file(storage_path('app/router-installed'))) {
            return true;
        }

        try {
            return Schema::hasTable('users') && User::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return resource|false */
    private function acquireInstallLock()
    {
        $path = storage_path('app/router-install.lock');
        $handle = @fopen($path, 'c+b');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return false;
        }

        @chmod($path, 0600);

        return $handle;
    }

    private function writePrivateFile(string $path, string $contents): void
    {
        $temporaryPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new \RuntimeException;
        }
        @chmod($temporaryPath, 0600);
        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new \RuntimeException;
        }
        @chmod($path, 0600);
    }

    private function quoteEnvValue(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
