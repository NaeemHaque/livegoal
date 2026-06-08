<?php

namespace App\Console\Commands;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * The single site-wide live poller. Fetches all in-play/paused matches in one
 * upstream request and writes them to cache so every visitor reads from cache
 * (see docs/LIVE_POLLING.md). Runs every minute via the scheduler.
 */
class PollLiveScores extends Command
{
    /** Cache key holding the live payload that GET /api/live serves. */
    public const CACHE_KEY = 'live:matches';

    protected $signature = 'app:poll-live-scores';

    protected $description = 'Poll in-play matches from football-data.org into cache for the whole site';

    public function handle(FootballData $football, Normalizer $normalizer): int
    {
        $raw = $football->get('/matches', ['status' => 'IN_PLAY,PAUSED']);

        // Upstream unavailable: keep the last good cache rather than blanking it.
        if ($raw === null) {
            Log::warning('PollLiveScores: upstream unavailable, keeping last live cache');
            $this->warn('Upstream unavailable; kept last live cache.');

            return self::SUCCESS;
        }

        $matches = $normalizer->matches($raw);
        $prior = $this->priorScores();
        $changed = [];

        foreach ($matches as $i => $m) {
            $id = $this->str($m['id'] ?? null);
            $previous = $prior[$id] ?? null;

            $matches[$i]['minute'] = $this->liveMinute($m);
            $matches[$i]['prevHomeScore'] = $previous['home'] ?? null;
            $matches[$i]['prevAwayScore'] = $previous['away'] ?? null;

            if ($previous !== null && ($m['homeScore'] !== $previous['home'] || $m['awayScore'] !== $previous['away'])) {
                $changed[] = $matches[$i];
            }
        }

        Cache::put(self::CACHE_KEY, [
            'matches' => $matches,
            'count' => count($matches),
            'lastUpdated' => Date::now()->toIso8601String(),
        ], Config::integer('football.ttl.live'));

        // ---- Future broadcast extension point (NOT wired in v1) ----
        // When Reverb is added on a VPS, dispatch a real-time event per changed
        // score here; nothing else in the polling flow needs to change:
        //
        //     foreach ($changed as $m) {
        //         broadcast(new \App\Events\ScoreUpdated($m));
        //     }

        $this->info(sprintf('Live: %d match(es), %d score change(s).', count($matches), count($changed)));

        return self::SUCCESS;
    }

    /**
     * Map of match id => prior score, from the previous poll's cache.
     *
     * @return array<string, array{home: mixed, away: mixed}>
     */
    private function priorScores(): array
    {
        $previous = Cache::get(self::CACHE_KEY);
        $scores = [];

        if (is_array($previous) && is_array($previous['matches'] ?? null)) {
            foreach ($previous['matches'] as $m) {
                if (is_array($m) && isset($m['id'])) {
                    $scores[$this->str($m['id'])] = [
                        'home' => $m['homeScore'] ?? null,
                        'away' => $m['awayScore'] ?? null,
                    ];
                }
            }
        }

        return $scores;
    }

    /**
     * Approximate live minute from kickoff (free tier has no real minute).
     *
     * @param  array<string, mixed>  $m
     */
    private function liveMinute(array $m): ?int
    {
        $status = $m['status'] ?? null;

        if ($status === 'HT') {
            return 45;
        }

        $kickoff = $m['kickoff'] ?? null;

        if ($status !== 'LIVE' || ! is_string($kickoff)) {
            return null;
        }

        $elapsed = (int) floor((Date::now()->getTimestamp() - Date::parse($kickoff)->getTimestamp()) / 60);

        return max(1, min($elapsed, 120));
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
