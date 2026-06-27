<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Date;

/**
 * Bridges ESPN's near-real-time live data onto the site-wide live feed.
 *
 * The football-data.org free tier that drives the poller lags minutes behind
 * play (a match can still read PAUSED/half-time on the home "Live now" rail
 * while ESPN already shows the second half). The match-detail page overlays
 * ESPN per match; this brings the same freshness to the live LIST.
 *
 * Two halves, deliberately split so the request path never touches the wire:
 *  - harvest() fetches ESPN for the in-play set (scheduled command only).
 *  - overlay() merges the harvested map onto matches with NO upstream call, so
 *    GET /api/live stays strictly cache-served.
 */
class EspnLiveOverlay
{
    public function __construct(
        private readonly EspnFootball $espn,
        private readonly EspnNormalizer $normalizer,
    ) {}

    /**
     * Fetch ESPN live data for each in-play match, keyed by our match id. Only
     * matches ESPN resolves are included. Has upstream calls — run it from the
     * scheduled harvest command, never from a request.
     *
     * @param  list<array<array-key, mixed>>  $matches
     * @return array<string, array{status: string, minute: ?int, displayClock: ?string, homeScore: ?int, awayScore: ?int}>
     */
    public function harvest(array $matches): array
    {
        $map = [];

        foreach ($matches as $match) {
            $id = $this->str($match['id'] ?? null);

            if ($id === '') {
                continue;
            }

            $live = $this->liveFor($match);

            if ($live !== null) {
                $map[$id] = $live;
            }
        }

        return $map;
    }

    /**
     * Overlay a harvested ESPN map onto a list of matches — pure, no upstream.
     * Mirrors the match-detail overlay: ESPN is authoritative for the live
     * status/minute/score, but a stale/pre-match ESPN scoreboard must never
     * downgrade an already-started match back to SCHEDULED (forward transitions
     * like LIVE→HT/FT are still taken).
     *
     * @param  list<array<array-key, mixed>>  $matches
     * @param  array<array-key, mixed>  $espnMap  Untrusted cache payload; non-array entries are ignored.
     * @return list<array<array-key, mixed>>
     */
    public function overlay(array $matches, array $espnMap): array
    {
        return array_map(function (array $match) use ($espnMap): array {
            $espn = $espnMap[$this->str($match['id'] ?? null)] ?? null;

            if (! is_array($espn)) {
                return $match;
            }

            $started = in_array($match['status'] ?? null, ['LIVE', 'HT', 'ET', 'PEN', 'FT'], true);
            $espnStatus = $espn['status'] ?? null;

            return [
                ...$match,
                'status' => ($started && $espnStatus === 'SCHEDULED')
                    ? ($match['status'] ?? null)
                    : ($espnStatus ?? $match['status'] ?? null),
                'minute' => $espn['minute'] ?? ($match['minute'] ?? null),
                'displayClock' => $espn['displayClock'] ?? null,
                'homeScore' => $espn['homeScore'] ?? ($match['homeScore'] ?? null),
                'awayScore' => $espn['awayScore'] ?? ($match['awayScore'] ?? null),
            ];
        }, $matches);
    }

    /**
     * Resolve one football-data match to ESPN's live status/minute/score (no
     * event summary — list views don't need the timeline), or null. ESPN buckets
     * a fixture by its own calendar day, so probe the kickoff day and neighbours
     * (each scoreboard is briefly cached) — mirrors MatchController::espn.
     *
     * @param  array<array-key, mixed>  $match
     * @return array{status: string, minute: ?int, displayClock: ?string, homeScore: ?int, awayScore: ?int}|null
     */
    private function liveFor(array $match): ?array
    {
        $slug = $this->espn->slugFor($this->str(data_get($match, 'competition.code')));
        $kickoff = $this->str(data_get($match, 'kickoff'));

        if ($slug === null || $kickoff === '') {
            return null;
        }

        $teams = [
            'home' => ['tla' => $this->str(data_get($match, 'home.tla')), 'name' => $this->str(data_get($match, 'home.name'))],
            'away' => ['tla' => $this->str(data_get($match, 'away.tla')), 'name' => $this->str(data_get($match, 'away.name'))],
        ];

        $kickoffDate = Date::parse($kickoff);

        foreach ([0, -1, 1] as $offset) {
            $date = $kickoffDate->copy()->addDays($offset)->toDateString();
            $resolved = $this->normalizer->resolve($this->espn->scoreboard($slug, $date), $teams);

            if ($resolved !== null) {
                $data = $this->normalizer->liveData($resolved, null);

                return [
                    'status' => $data['status'],
                    'minute' => $data['minute'],
                    'displayClock' => $data['displayClock'],
                    'homeScore' => $data['homeScore'],
                    'awayScore' => $data['awayScore'],
                ];
            }
        }

        return null;
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
