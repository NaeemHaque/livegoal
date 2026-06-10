<?php

namespace App\Console\Commands;

use App\Services\Football\FootballData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Warms the featured competitions' match feeds so GET /matches/upcoming and
 * /matches/day always serve from cache. Without this, the first reload after the
 * 10-minute match cache expires fans out up to eight rate-limited upstream calls
 * — a cold aggregation can take longer than the browser's request timeout and
 * blank the homepage's "Upcoming" section.
 *
 * Self-paced: only stale feeds are refreshed, at most MAX_PER_RUN per run, so the
 * every-minute schedule keeps every feed warm while staying inside the free-tier
 * rate limit (the most-important feed — the current competition — is first).
 */
class WarmMatches extends Command
{
    protected $signature = 'app:warm-matches';

    protected $description = "Warm the featured competitions' match feeds for the homepage";

    /** Cap upstream fetches per run so the every-minute schedule stays rate-limit safe. */
    private const MAX_PER_RUN = 4;

    public function handle(FootballData $football): int
    {
        $ttl = Config::integer('football.ttl.matches');
        $warmed = 0;

        foreach ($this->codes() as $code) {
            if ($warmed >= self::MAX_PER_RUN) {
                break;
            }

            // Still-fresh feeds need no work; skip them without spending a slot.
            if (Cache::has("fd:competition:{$code}:matches")) {
                continue;
            }

            $football->cached("competition:{$code}:matches", $ttl, "/competitions/{$code}/matches");
            $warmed++;
        }

        $this->info(sprintf('Warmed %d match feed(s).', $warmed));

        return self::SUCCESS;
    }

    /**
     * Featured competition codes — the set GET /matches/upcoming aggregates.
     *
     * @return list<string>
     */
    private function codes(): array
    {
        $codes = [];

        foreach (Config::array('football.featured') as $code) {
            if (is_string($code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
