<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Config;

/**
 * Maps football-data.org v4 responses into SocPlay's normalized DTO arrays
 * (see docs/DATA_MODEL.md). Upstream values are `mixed`, so every field is read
 * through the typed coercion helpers below.
 */
class Normalizer
{
    /**
     * @param  array<array-key, mixed>  $payload  Upstream /competitions response.
     * @return list<array<string, mixed>>
     */
    public function competitions(array $payload): array
    {
        $items = $payload['competitions'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (array $c): array => $this->competition($c),
            array_filter($items, is_array(...)),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $c  A single upstream competition.
     * @return array<string, mixed>
     */
    public function competition(array $c): array
    {
        $code = $this->str($c['code'] ?? null);
        $meta = Config::array("football.meta.{$code}", []);
        $type = strtoupper($this->str($c['type'] ?? null));

        return [
            'id' => $this->str($c['id'] ?? null),
            'code' => $code,
            'name' => $this->str($c['name'] ?? null),
            'short' => $this->str($meta['short'] ?? null) ?: $this->str($c['name'] ?? null),
            'region' => $this->str(data_get($c, 'area.name')),
            'kind' => match ($type) {
                'CUP' => 'cup',
                'LEAGUE' => 'league',
                default => $this->str($meta['kind'] ?? null) ?: 'league',
            },
            'color' => $this->str($meta['color'] ?? null) ?: '#64748B',
            'featured' => ($meta['featured'] ?? false) === true,
            'emblem' => $this->nullableStr(data_get($c, 'emblem')),
        ];
    }

    /** Coerce a mixed value to a string ('' when not scalar). */
    protected function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** Coerce a mixed value to a string, or null when absent/non-scalar. */
    protected function nullableStr(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
