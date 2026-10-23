<?php

namespace App\Services;

class PosHealthTransport
{
    public const MAX_RESPONSE_BYTES = 16_384;

    /** @param list<string> $addresses */
    public function get(string $url, string $host, int $port, array $addresses): PosHealthResponse
    {
        if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
            throw new PosEndpointException('TRANSPORT_UNAVAILABLE', 'Secure endpoint checks are unavailable on this server.');
        }

        $pinnedAddresses = array_map(
            fn (string $address): string => str_contains($address, ':') ? '['.$address.']' : $address,
            $addresses,
        );
        $curlResolve = [$host.':'.$port.':'.implode(',', $pinnedAddresses)];
        $body = '';
        $overflow = false;
        $startedAt = hrtime(true);
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => $curlResolve,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'SiyazPOS-Router-Health-Check/1.0',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$overflow): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $overflow = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $result = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);

        $elapsedMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($overflow) {
            throw new PosEndpointException('RESPONSE_TOO_LARGE', 'The endpoint response exceeded the allowed size.', $elapsedMs);
        }

        if ($result === false) {
            throw new PosEndpointException($curlError === CURLE_OPERATION_TIMEDOUT ? 'TIMEOUT' : 'CONNECTION_FAILED', $curlError === CURLE_OPERATION_TIMEDOUT ? 'The endpoint request timed out.' : 'The endpoint did not respond.', $elapsedMs);
        }

        return new PosHealthResponse($statusCode, $body, $elapsedMs);
    }
}
