<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Privacy-friendly, cookieless analytics. The tracking script only renders
    // when a domain is set (so dev/tests stay clean); Plausible's default script
    // tracks SPA pushState navigations automatically.
    'plausible' => [
        'domain' => env('PLAUSIBLE_DOMAIN'),
        'src' => env('PLAUSIBLE_SRC', 'https://plausible.io/js/script.js'),
    ],

    // Google Analytics (GA4). The gtag snippet only renders when a measurement
    // ID is set, so dev/tests stay out of the property. GA4 enhanced measurement
    // tracks SPA history (pushState) navigations automatically.
    'google_analytics' => [
        'id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    // IndexNow: instant URL submission to Bing / Yandex / Seznam when a result
    // changes. Disabled until a key is set (generate any 16–128 char hex string
    // as INDEXNOW_KEY); the key is also served at /{key}.txt for verification.
    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    ],

];
