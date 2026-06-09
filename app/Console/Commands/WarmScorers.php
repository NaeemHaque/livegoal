<?php

namespace App\Console\Commands;

use App\Services\Football\FootballData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Warms the top-scorers feed for the featured leagues into cache so the Top
 * Scorers tabs (and the Live hub rail) are near-instant cache hits instead of
 * slow on-demand football-data.org calls — the free tier is rate-limited, so a
 * cold tab can stall for seconds. FootballData::cached() no-ops when an entry is
 * still fresh, so most runs make zero upstream calls; only an expired feed (30m
 * TTL) is refetched.
 */
class WarmScorers extends Command
{
    protected $signature = 'app:warm-scorers';

    protected $description = 'Warm the top-scorers cache for the featured leagues';

    public function handle(FootballData $football): int
    {
        $ttl = Config::integer('football.ttl.scorers');
        $codes = $this->leagueCodes();

        foreach ($codes as $code) {
            // Same cache key the on-demand endpoint uses (competition:{code}:scorers,
            // no query), so a warmed entry is served directly to visitors.
            $football->cached("competition:{$code}:scorers", $ttl, "/competitions/{$code}/scorers");
        }

        $this->info(sprintf('Warmed scorers for %d league(s): %s', count($codes), implode(', ', $codes)));

        return self::SUCCESS;
    }

    /**
     * Featured competition codes that are leagues. Cups (World Cup, Champions
     * League) have no season-long Golden Boot race worth pre-warming for the tabs.
     *
     * @return list<string>
     */
    private function leagueCodes(): array
    {
        $meta = Config::array('football.meta');
        $codes = [];

        foreach (Config::array('football.featured') as $code) {
            if (! is_string($code)) {
                continue;
            }

            $entry = $meta[$code] ?? null;

            if (is_array($entry) && ($entry['kind'] ?? null) === 'league') {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
