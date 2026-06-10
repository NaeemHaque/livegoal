<?php

namespace App\Seo;

use Illuminate\Support\Str;

/**
 * Builds keyword-rich, ID-first entity URLs (e.g. /match/524-arsenal-vs-chelsea)
 * and extracts the numeric ID back out. ID-first keeps lookups trivial and the
 * slug purely cosmetic/canonical; football-data IDs are stable, so the slug can
 * change without breaking the page.
 */
class Slug
{
    /** Path for an entity, e.g. ('match', '524', 'Arsenal vs Chelsea') -> "/match/524-arsenal-vs-chelsea". */
    public static function path(string $prefix, string $id, ?string $name): string
    {
        $slug = Str::slug((string) $name);

        return $slug === '' ? "/{$prefix}/{$id}" : "/{$prefix}/{$id}-{$slug}";
    }

    /** Absolute URL for an entity (see path()). */
    public static function url(string $prefix, string $id, ?string $name): string
    {
        return url(self::path($prefix, $id, $name));
    }

    /** The leading numeric ID from a (possibly slugged) route param: "524-arsenal" -> "524". */
    public static function id(string $param): string
    {
        return preg_match('/^\d+/', $param, $matches) === 1 ? $matches[0] : $param;
    }
}
