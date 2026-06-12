<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Config;

/**
 * Aggregates the featured competitions' (cached) match feeds into one list.
 *
 * The free-tier global /matches feed only returns currently-active competitions,
 * so the day/upcoming views merge each featured competition's scoped feed. Shared
 * by MatchController (which may populate a cold feed once) and the SEO shell
 * (crawler-safe: cache-only, never hits upstream).
 */
class FeaturedMatches
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
    ) {}

    /**
     * Merge every featured competition's matches.
     *
     * @param  bool  $allowFetch  When true (browser API), a truly cold feed may be
     *                            fetched once (FootballData::cached, refresh:false).
     *                            When false (crawlers), reads are cache-only (peek).
     * @return array{matches: list<array<string, mixed>>, lastUpdated: string|null, stale: bool, served: bool}
     */
    public function all(bool $allowFetch = true): array
    {
        $ttl = Config::integer('football.ttl.matches');

        $matches = [];
        $lastUpdated = null;
        $stale = false;
        $served = false;

        foreach (Config::array('football.featured') as $code) {
            if (! is_string($code)) {
                continue;
            }

            $key = "competition:{$code}:matches";

            if ($allowFetch) {
                $result = $this->football->cached($key, $ttl, "/competitions/{$code}/matches", [], refresh: false);
                $data = is_array($result->data) ? $result->data : null;
                $at = $result->lastUpdated;
                $stale = $stale || $result->stale;
            } else {
                $entry = $this->football->peekEntry($key);
                $data = $entry['data'] ?? null;
                $at = $entry['at'] ?? null;
            }

            if ($data !== null) {
                $served = true;
                $matches = array_merge($matches, $this->normalizer->matches($data));
            }

            if (is_string($at) && ($lastUpdated === null || $at > $lastUpdated)) {
                $lastUpdated = $at;
            }
        }

        return ['matches' => $matches, 'lastUpdated' => $lastUpdated, 'stale' => $stale, 'served' => $served];
    }

    /**
     * Matches kicking off on the given date (Y-m-d), soonest first.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    public function onDate(array $matches, string $date): array
    {
        return $this->sortByKickoff(array_values(array_filter(
            $matches,
            fn (array $m): bool => $this->day($m) === $date,
        )));
    }

    /**
     * Next scheduled fixtures from the given date onward, soonest first, limited.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    public function scheduledFrom(array $matches, string $date, int $limit): array
    {
        $upcoming = array_values(array_filter(
            $matches,
            fn (array $m): bool => ($m['status'] ?? null) === 'SCHEDULED' && $this->day($m) >= $date,
        ));

        return array_slice($this->sortByKickoff($upcoming), 0, $limit);
    }

    /**
     * Every scheduled fixture inside a date window, soonest first — no count
     * cap, so a full tournament schedule (e.g. all 104 World Cup fixtures)
     * comes through whole.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    public function scheduledWindow(array $matches, string $from, string $until): array
    {
        $upcoming = array_values(array_filter(
            $matches,
            fn (array $m): bool => ($m['status'] ?? null) === 'SCHEDULED'
                && $this->day($m) >= $from
                && $this->day($m) <= $until,
        ));

        return $this->sortByKickoff($upcoming);
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    public function sortByKickoff(array $matches): array
    {
        $kickoff = fn (array $m): string => is_string($m['kickoff'] ?? null) ? $m['kickoff'] : '';
        usort($matches, fn (array $a, array $b): int => strcmp($kickoff($a), $kickoff($b)));

        return $matches;
    }

    /**
     * The kickoff date (Y-m-d) of a normalized match, or '' when unknown.
     *
     * @param  array<string, mixed>  $match
     */
    private function day(array $match): string
    {
        $kickoff = $match['kickoff'] ?? null;

        return is_string($kickoff) ? substr($kickoff, 0, 10) : '';
    }
}
