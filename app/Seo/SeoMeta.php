<?php

namespace App\Seo;

/**
 * Per-URL SEO metadata for the server-rendered SPA shell. Built by
 * SeoMetaResolver from cached football data and consumed by resources/views/app.blade.php.
 */
class SeoMeta
{
    /**
     * @param  string  $title  Full <title>, brand suffix included.
     * @param  string  $description  Meta description (~155 chars).
     * @param  string  $canonical  Absolute canonical URL (query string stripped).
     * @param  string  $robots  Robots directive, e.g. "index,follow" or "noindex,follow".
     * @param  string  $ogType  Open Graph object type ("website", "profile", ...).
     * @param  list<array<string, mixed>>  $jsonLd  JSON-LD blocks to emit as <script> tags.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly string $robots = 'index,follow',
        public readonly string $ogType = 'website',
        public readonly array $jsonLd = [],
    ) {}

    /**
     * Whether this page should be indexed (drives the Open Graph emission too).
     */
    public function isIndexable(): bool
    {
        return ! str_contains($this->robots, 'noindex');
    }

    /**
     * Each JSON-LD block, encoded so it is safe to drop inside a <script> element
     * (tags hex-escaped so a value can never break out with "</script>").
     *
     * @return list<string>
     */
    public function jsonLdScripts(): array
    {
        return array_map(
            fn (array $block): string => (string) json_encode(
                $block,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
            ),
            $this->jsonLd,
        );
    }
}
