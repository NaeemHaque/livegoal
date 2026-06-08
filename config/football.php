<?php

return [
    /*
    |--------------------------------------------------------------------------
    | football-data.org v4
    |--------------------------------------------------------------------------
    | The token is server-side only — never exposed to the browser. The Laravel
    | backend is the single poller/proxy; see docs/ARCHITECTURE.md.
    */

    'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),
    'token' => env('FOOTBALL_DATA_TOKEN', ''),
    'timeout' => (int) env('FOOTBALL_DATA_TIMEOUT', 10),
    'connect_timeout' => (int) env('FOOTBALL_DATA_CONNECT_TIMEOUT', 3),

    // Number of retries on a 429 / connection failure before giving up.
    'retries' => (int) env('FOOTBALL_DATA_RETRIES', 2),
    'retry_delay_ms' => (int) env('FOOTBALL_DATA_RETRY_DELAY_MS', 1500),

    // Free-tier competition codes (football-data.org).
    'competitions' => ['WC', 'CL', 'PL', 'PD', 'SA', 'BL1', 'FL1', 'DED', 'PPL', 'ELC', 'EC', 'BSA', 'CLI'],

    /*
    | Display meta keyed by upstream competition code (short name, brand color,
    | featured flag). The upstream API doesn't provide these, so the design's
    | values live here. `kind` falls back to the upstream `type` when absent.
    */
    'meta' => [
        'WC' => ['short' => 'World Cup', 'color' => '#C6FF3A', 'kind' => 'cup', 'featured' => true],
        'CL' => ['short' => 'Champions Lg', 'color' => '#0B1B6F', 'kind' => 'cup'],
        'EC' => ['short' => 'Euros', 'color' => '#0B1B6F', 'kind' => 'cup'],
        'CLI' => ['short' => 'Libertadores', 'color' => '#0B1B6F', 'kind' => 'cup'],
        'PL' => ['short' => 'Premier Lg', 'color' => '#37003C', 'kind' => 'league'],
        'ELC' => ['short' => 'Championship', 'color' => '#1B458F', 'kind' => 'league'],
        'PD' => ['short' => 'LaLiga', 'color' => '#E30613', 'kind' => 'league'],
        'SA' => ['short' => 'Serie A', 'color' => '#0067B1', 'kind' => 'league'],
        'BL1' => ['short' => 'Bundesliga', 'color' => '#D20515', 'kind' => 'league'],
        'FL1' => ['short' => 'Ligue 1', 'color' => '#091C3E', 'kind' => 'league'],
        'DED' => ['short' => 'Eredivisie', 'color' => '#FF6200', 'kind' => 'league'],
        'PPL' => ['short' => 'Primeira Liga', 'color' => '#1B7A3D', 'kind' => 'league'],
        'BSA' => ['short' => 'Brasileirão', 'color' => '#009739', 'kind' => 'league'],
    ],

    /*
    | Cache TTLs in seconds (see docs/API.md). Live data is written by the
    | poller; everything else is filled on demand via FootballData::cached().
    */
    'ttl' => [
        'live' => 70,
        'competitions' => 86400,      // 24h
        'competition' => 86400,
        'standings' => 600,           // 10m
        'matches' => 600,
        'match_live' => 45,
        'match_finished' => 600,
        'scorers' => 1800,            // 30m
        'teams' => 43200,             // 12h
        'team' => 86400,
        'team_matches' => 600,
        'person' => 86400,
    ],

    // How long a last-known-good payload is retained for stale-on-failure fallback.
    'last_good_ttl' => 86400,
];
