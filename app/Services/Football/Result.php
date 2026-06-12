<?php

namespace App\Services\Football;

/**
 * Outcome of a cache-served upstream read. Carries enough signal for the API
 * layer to build its `meta` envelope (see docs/API.md).
 */
final class Result
{
    /**
     * @param  array<array-key, mixed>|null  $data  Decoded payload, or null on a hard miss.
     * @param  bool  $stale  True when served from last-known-good after an upstream failure.
     * @param  bool  $cached  True when served from cache (vs a fresh upstream fill).
     * @param  string|null  $lastUpdated  ISO-8601 timestamp of when the payload was fetched.
     */
    public function __construct(
        public readonly ?array $data,
        public readonly bool $stale = false,
        public readonly bool $cached = false,
        public readonly ?string $lastUpdated = null,
    ) {}
}
