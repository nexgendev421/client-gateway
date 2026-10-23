<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Supplies a short-lived session-encryption key only while the first-run installer is open. */
class SetInstallerSessionKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('install')) {
            return $next($request);
        }

        // Keep install sessions and rate limits on local files before the database exists.
        config(['session.driver' => 'file', 'cache.default' => 'file']);

        if (filled(config('app.key'))) {
            return $next($request);
        }

        $keyPath = storage_path('app/installer-session.key');
        $directory = dirname($keyPath);

        try {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException;
            }

            $handle = fopen($keyPath, 'c+b');
            if ($handle === false) {
                throw new \RuntimeException;
            }

            try {
                if (! flock($handle, LOCK_EX)) {
                    throw new \RuntimeException;
                }

                if (filesize($keyPath) === 0) {
                    fwrite($handle, 'base64:'.base64_encode(random_bytes(32)));
                    fflush($handle);
                    @chmod($keyPath, 0600);
                }

                rewind($handle);
                $key = trim((string) stream_get_contents($handle));
                if (! str_starts_with($key, 'base64:') || strlen($key) < 40) {
                    throw new \RuntimeException;
                }
                config(['app.key' => $key]);
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable) {
            abort(503, 'The installer is temporarily unavailable.');
        }

        return $next($request);
    }
}
