<?php

namespace App\Services;

final readonly class PosHealthResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public int $responseTimeMs,
    ) {}
}
