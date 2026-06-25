<?php

namespace App\Console\Commands;

use App\Services\Football\EspnLiveOverlay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Harvests ESPN's near-real-time live data for the in-play set the poller has
 * surfaced, into a side cache the live API merges at read time. Kept separate
 * from PollLiveScores so the (football-data) poller and GET /api/live are never
 * touched — the poller stays the source of truth for *which* matches are live,
 * ESPN just freshens their status/minute/score. See EspnLiveOverlay.
 */
class HarvestLiveEspn extends Command
{
    /** Cache key holding the ESPN overlay map (our match id => live fields). */
    public const OVERLAY_KEY = 'live:espn';

    /** Short TTL so a stopped harvest stops overlaying within a couple of cycles. */
    public const OVERLAY_TTL = 120;

    protected $signature = 'app:harvest-live-espn';

    protected $description = 'Harvest ESPN live data for in-play matches into the live-overlay cache';

    public function handle(EspnLiveOverlay $overlay): int
    {
        $payload = Cache::get(PollLiveScores::CACHE_KEY);
        $matches = is_array($payload) && is_array($payload['matches'] ?? null)
            ? array_values(array_filter($payload['matches'], is_array(...)))
            : [];

        // Nothing live → clear the overlay so a stale map can't linger, and skip
        // the wire entirely.
        if ($matches === []) {
            Cache::forget(self::OVERLAY_KEY);
            $this->info('No live matches — ESPN overlay cleared.');

            return self::SUCCESS;
        }

        $map = $overlay->harvest($matches);

        Cache::put(self::OVERLAY_KEY, $map, self::OVERLAY_TTL);

        $this->info(sprintf('ESPN overlay refreshed for %d of %d live match(es).', count($map), count($matches)));

        return self::SUCCESS;
    }
}
