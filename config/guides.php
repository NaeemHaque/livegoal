<?php

/*
|--------------------------------------------------------------------------
| Editorial content pages (guides, explainers, trust)
|--------------------------------------------------------------------------
| Server-rendered evergreen content — the AEO/GEO layer (clear factual
| answers) the live-data app otherwise lacks. Keyed by slug; rendered by
| App\Http\Controllers\ContentController through the layouts.content layout.
| `listed` controls appearance in the /guides index (trust pages live in the
| footer instead).
*/

return [
    'world-cup-2026-format-explained' => [
        'path' => '/guides/world-cup-2026-format-explained',
        'title' => 'World Cup 2026 Format Explained: 48 Teams, 12 Groups',
        'description' => 'How the 2026 FIFA World Cup works: 48 teams, 12 groups of four, 104 matches across the USA, Canada and Mexico, and how the new 32-team knockout round fits in.',
        'view' => 'content.guides.world-cup-format',
        'group' => 'World Cup 2026',
        'nav' => 'World Cup 2026 format',
        'listed' => true,
        'live' => 'WC',
    ],

    'world-cup-2026-groups-and-qualification' => [
        'path' => '/guides/world-cup-2026-groups-and-qualification',
        'title' => 'World Cup 2026 Groups & How Teams Qualify',
        'description' => 'How the 2026 World Cup group stage works: who advances from each of the 12 groups, the eight best third-placed teams, and the exact FIFA tiebreaker order.',
        'view' => 'content.guides.world-cup-groups',
        'group' => 'World Cup 2026',
        'nav' => 'Groups & qualification',
        'listed' => true,
        'live' => 'WC',
    ],

    'world-cup-2026-knockout-bracket-explained' => [
        'path' => '/guides/world-cup-2026-knockout-bracket-explained',
        'title' => 'World Cup 2026 Knockout Bracket Explained',
        'description' => 'The 2026 World Cup knockout stage: the new Round of 32 through to the final at MetLife Stadium, plus how extra time and penalty shootouts decide a winner.',
        'view' => 'content.guides.world-cup-knockout',
        'group' => 'World Cup 2026',
        'nav' => 'Knockout bracket',
        'listed' => true,
        'live' => 'WC',
    ],

    'how-to-watch-world-cup-2026-free' => [
        'path' => '/guides/how-to-watch-world-cup-2026-free',
        'title' => 'How to Watch the World Cup 2026 Free',
        'description' => 'Where to watch the 2026 World Cup free-to-air by country — the USA, UK, Canada, Australia, India, Mexico, Germany, France and Brazil — and how much of the tournament is free.',
        'view' => 'content.guides.how-to-watch',
        'group' => 'World Cup 2026',
        'nav' => 'How to watch free',
        'listed' => true,
    ],

    'football-glossary' => [
        'path' => '/guides/football-glossary',
        'title' => 'Football Glossary: Key Terms Explained',
        'description' => 'Plain-English definitions of common football terms — offside, goal difference, clean sheet, extra time, penalty shootout, VAR, aggregate and more.',
        'view' => 'content.guides.football-glossary',
        'group' => 'Football basics',
        'nav' => 'Football glossary',
        'listed' => true,
    ],

    'about' => [
        'path' => '/about',
        'title' => 'About LiveGoal',
        'description' => 'LiveGoal is a free, fast, no-betting live football scores site for the World Cup 2026 and major leagues — fixtures, results, standings and brackets.',
        'view' => 'content.pages.about',
        'group' => 'LiveGoal',
        'nav' => 'About',
        'listed' => false,
    ],

    'how-our-data-works' => [
        'path' => '/how-our-data-works',
        'title' => "How LiveGoal's Data Works",
        'description' => 'Where LiveGoal gets its football data, how often scores update, and why the site stays fast and ad-free with no betting content.',
        'view' => 'content.pages.data',
        'group' => 'LiveGoal',
        'nav' => 'How our data works',
        'listed' => false,
    ],

    'contact' => [
        'path' => '/contact',
        'title' => 'Contact LiveGoal',
        'description' => 'Get in touch with LiveGoal — report a data issue, suggest a feature, or ask a question.',
        'view' => 'content.pages.contact',
        'group' => 'LiveGoal',
        'nav' => 'Contact',
        'listed' => false,
    ],
];
