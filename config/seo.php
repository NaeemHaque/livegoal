<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SEO defaults
    |--------------------------------------------------------------------------
    | Site-wide metadata for the server-rendered SEO shell (see
    | App\Seo\SeoMetaResolver). Per-page title/description/canonical/JSON-LD are
    | derived from cached football data; these are the fallbacks and brand bits.
    */

    'site_name' => env('SEO_SITE_NAME', 'LiveGoal'),

    'default_title' => env(
        'SEO_DEFAULT_TITLE',
        'World Cup 2026 Scores & Live Football Results | LiveGoal',
    ),

    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Live football scores in real time: World Cup 2026, Premier League, La Liga, '
        .'Serie A, Bundesliga and more. Free fixtures, results, standings and knockout '
        .'brackets — no betting ads, no clutter.',
    ),

    // Open Graph / Twitter share image — a bundled 1200x630 og-image.png
    // (regenerate with `php artisan og:generate`). Override to rebrand.
    'og_image' => env('SEO_OG_IMAGE', '/og-image.png'),

    // The default share image is 1200x630, so use the large-image Twitter card.
    'og_image_wide' => (bool) env('SEO_OG_IMAGE_WIDE', true),

    // Twitter/X handle (with @), or null to omit the tag.
    'twitter' => env('SEO_TWITTER_HANDLE'),

    'locale' => env('SEO_LOCALE', 'en_US'),

    // Logo used in Organization JSON-LD (resolved to an absolute URL).
    'organization_logo' => env('SEO_ORGANIZATION_LOGO', '/apple-touch-icon.png'),
];
