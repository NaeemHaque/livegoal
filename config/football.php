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
