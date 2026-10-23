<?php

namespace App\Services;

class PosEndpointGuard
{
    public function validationError(string $url, bool $production): ?string
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return 'The endpoint URL contains invalid characters or is too long.';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'Enter a valid endpoint URL.';
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['https', 'http'], true) || ($production && $scheme !== 'https')) {
            return 'The endpoint must use HTTPS in production.';
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return 'The endpoint URL must not contain credentials, a query string, or a fragment.';
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host !== strtolower($parts['host'])
            || filter_var($host, FILTER_VALIDATE_IP)
            || preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/i', $host)
            || ! str_contains($host, '.')
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return 'Use a public fully qualified hostname instead of an IP address or local hostname.';
        }

        foreach (['.localhost', '.local', '.internal', '.lan', '.test', '.example', '.invalid'] as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return 'Local, reserved, and internal hostnames are not allowed.';
            }
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true) || ($production && $port !== 443)) {
            return 'Use the standard HTTPS endpoint port in production.';
        }

        return null;
    }

    /** @return list<string> */
    public function resolvePublicAddresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($address !== null) {
                $addresses[] = $address;
            }
        }

        $addresses = array_values(array_unique($addresses));
        if ($addresses === []) {
            throw new PosEndpointException('DNS_LOOKUP_FAILED', 'The endpoint hostname could not be resolved.');
        }

        $this->assertPublicAddresses($addresses);

        return $addresses;
    }

    /** @param list<string> $addresses */
    public function assertPublicAddresses(array $addresses): void
    {
        if ($addresses === []) {
            throw new PosEndpointException('DNS_LOOKUP_FAILED', 'The endpoint hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new PosEndpointException('ENDPOINT_ADDRESS_BLOCKED', 'The endpoint resolves to a non-public network address.');
            }
        }
    }
}
