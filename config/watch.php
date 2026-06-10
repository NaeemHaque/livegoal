<?php

/*
|--------------------------------------------------------------------------
| "How to watch the World Cup 2026 free" — one page per country
|--------------------------------------------------------------------------
| Per-country pages win the long-tail ("watch world cup free in {country}")
| far better than one combined page. Each entry is research-verified against
| FIFA's media-rights list plus a dated broadcaster source (June 2026); the
| free-vs-pay `scope` is stated so the page is accurate, not over-promising.
| Served at /guides/how-to-watch-world-cup-2026-free/{country}.
*/

return [
    'uk' => [
        'country' => 'the UK',
        'free' => true,
        'fta' => 'BBC and ITV',
        'scope' => 'All 104 matches are free-to-air.',
        'streaming' => 'Every match streams free on BBC iPlayer and ITVX.',
        'paid' => null,
    ],
    'australia' => [
        'country' => 'Australia',
        'free' => true,
        'fta' => 'SBS',
        'scope' => 'All 104 matches are free-to-air on SBS, SBS Viceland and SBS On Demand.',
        'streaming' => 'Every match streams free on SBS On Demand.',
        'paid' => null,
    ],
    'mexico' => [
        'country' => 'Mexico',
        'free' => true,
        'fta' => 'TV Azteca and TelevisaUnivision (Canal 5)',
        'scope' => 'Matches are shown free-to-air nationwide across TV Azteca and TelevisaUnivision channels.',
        'streaming' => 'Selected matches stream free on the TV Azteca app.',
        'paid' => null,
    ],
    'usa' => [
        'country' => 'the USA',
        'free' => true,
        'fta' => 'FOX (English) and Telemundo (Spanish)',
        'scope' => 'Most matches air free over the air — in English on FOX/FS1 and in Spanish on Telemundo (92 of 104 matches).',
        'streaming' => 'Free streaming is limited; all 104 matches are on the paid FOX One (English) and Peacock (Spanish), with some free matches on Tubi.',
        'paid' => 'FOX One (English) and Peacock (Spanish) carry every match.',
    ],
    'germany' => [
        'country' => 'Germany',
        'free' => true,
        'fta' => 'ARD and ZDF',
        'scope' => 'Around 60 of the 104 matches are free-to-air on ARD and ZDF.',
        'streaming' => 'Free matches stream on the ARD and ZDF media libraries.',
        'paid' => 'Telekom\'s MagentaTV holds all 104 matches, including the games not shown on free TV.',
    ],
    'france' => [
        'country' => 'France',
        'free' => true,
        'fta' => 'M6',
        'scope' => 'Around 54 matches are free-to-air on M6.',
        'streaming' => 'Free matches stream on the M6+ platform.',
        'paid' => 'beIN Sports carries the remaining matches.',
    ],
    'brazil' => [
        'country' => 'Brazil',
        'free' => true,
        'fta' => 'TV Globo and CazéTV (YouTube)',
        'scope' => 'Selected matches are free-to-air on TV Globo, with free streaming on CazéTV (YouTube).',
        'streaming' => 'CazéTV streams selected matches free on YouTube.',
        'paid' => 'Remaining matches are on pay TV.',
    ],
    'canada' => [
        'country' => 'Canada',
        'free' => false,
        'fta' => 'CTV',
        'scope' => 'Only marquee games are free on CTV (the opener, Canada\'s matches and the final). Most matches require pay TV.',
        'streaming' => 'There is no full free stream; CTV shows selected games free.',
        'paid' => 'TSN, RDS and TSN+ carry all 104 matches.',
    ],
    'india' => [
        'country' => 'India',
        'free' => false,
        'fta' => 'DD Sports (DD Free Dish)',
        'scope' => 'Only mandated marquee games are free on DD Sports — the opener, quarter-finals, semi-finals and final.',
        'streaming' => 'Most matches stream on the paid ZEE5; only the marquee games are free.',
        'paid' => 'Zee\'s networks and ZEE5 carry the full tournament.',
    ],
    'bangladesh' => [
        'country' => 'Bangladesh',
        'free' => true,
        'fta' => 'BTV, T Sports and Somoy TV',
        'scope' => 'All 104 matches are shown on TV through the BTV, T Sports and Somoy TV consortium. State broadcaster BTV is free-to-air; T Sports and Somoy TV are carried on satellite and cable.',
        'streaming' => 'Streaming is via Toffee (Banglalink) and Bioscope (Grameenphone), which carry every match on a paid subscription — there is no fully free stream.',
        'paid' => 'Toffee and Bioscope subscription packages carry all 104 matches online.',
    ],
];
