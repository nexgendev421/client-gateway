<?php

namespace App\Services;

use App\Enums\HealthCheckStatus;
use App\Models\HealthCheck;
use App\Models\Store;
use JsonException;

class PosEndpointHealthChecker
{
    public function __construct(
        private readonly PosEndpointGuard $guard,
        private readonly PosHealthTransport $transport,
    ) {}

    public function check(Store $store): HealthCheck
    {
        $url = $store->endpoint_url;
        $error = $this->guard->validationError($url, true);
        if ($error !== null) {
            return $this->record($store, HealthCheckStatus::Offline, null, 'ENDPOINT_URL_INVALID', 'The registered endpoint URL is not allowed.');
        }

        $parts = parse_url($url);
        $host = strtolower(rtrim($parts['host'], '.'));
        $port = $parts['port'] ?? 443;
        $addresses = [];

        try {
            $addresses = $this->guard->resolvePublicAddresses($host);
            $healthUrl = rtrim($url, '/').'/api/router/health';
            $response = $this->transport->get($healthUrl, $host, $port, $addresses);

            if ($response->statusCode >= 300 && $response->statusCode < 400) {
                return $this->record($store, HealthCheckStatus::Offline, $response->responseTimeMs, 'REDIRECT_BLOCKED', 'The endpoint redirected the health request.');
            }

            if ($response->statusCode !== 200) {
                return $this->record($store, HealthCheckStatus::Offline, $response->responseTimeMs, 'HTTP_ERROR', 'The endpoint returned an unsuccessful response.');
            }

            try {
                $payload = json_decode($response->body, true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->record($store, HealthCheckStatus::Offline, $response->responseTimeMs, 'INVALID_RESPONSE', 'The endpoint returned an invalid health response.');
            }

            if (! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
                return $this->record($store, HealthCheckStatus::Offline, $response->responseTimeMs, 'INVALID_STATUS', 'The endpoint did not report a healthy status.');
            }

            return $this->record(
                $store,
                HealthCheckStatus::Online,
                $response->responseTimeMs,
                null,
                null,
                $this->safeVersion($payload['pos_version'] ?? null),
                $this->safeVersion($payload['api_version'] ?? null),
            );
        } catch (PosEndpointException $exception) {
            return $this->record($store, HealthCheckStatus::Offline, $exception->responseTimeMs, $exception->safeCode, $exception->getMessage());
        } catch (\Throwable) {
            return $this->record($store, HealthCheckStatus::Offline, null, 'CHECK_FAILED', 'The endpoint check could not be completed.');
        }
    }

    private function record(Store $store, HealthCheckStatus $status, ?int $elapsedMs, ?string $errorCode, ?string $errorMessage, ?string $posVersion = null, ?string $apiVersion = null): HealthCheck
    {
        return $store->healthChecks()->create([
            'status' => $status,
            'response_time_ms' => $elapsedMs,
            'pos_version' => $posVersion,
            'api_version' => $apiVersion,
            'checked_at' => now(),
            'safe_error_code' => $errorCode,
            'safe_error_message' => $errorMessage,
        ]);
    }

    private function safeVersion(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 100 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]*$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
