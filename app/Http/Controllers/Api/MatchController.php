<?php

namespace App\Http\Controllers\Api;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

class MatchController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'competition' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $date = $request->filled('date') ? (string) $request->string('date') : Date::now()->toDateString();
        $query = ['dateFrom' => $date, 'dateTo' => $date];

        if ($request->filled('competition')) {
            $query['competitions'] = strtoupper((string) $request->string('competition'));
        }

        if ($request->filled('status')) {
            $query['status'] = strtoupper((string) $request->string('status'));
        }

        return $this->respond(
            $this->football->cached('matches', Config::integer('football.ttl.matches'), '/matches', $query),
            $this->normalizer->matches(...),
        );
    }

    /**
     * A day's fixtures aggregated across the featured competitions, server-side.
     *
     * The free-tier global /matches feed only returns currently-active
     * competitions, so the browser would otherwise fan out one request per
     * competition. Here we merge each competition's (cached) scoped feed and
     * return a single response — one browser request instead of eight.
     */
    public function day(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $request->filled('date') ? (string) $request->string('date') : Date::now()->toDateString();

        // Filter the (shared, full-season) feed by date in PHP rather than per-date
        // upstream queries — so day, upcoming and Competition Detail all hit the
        // same cache and navigating dates costs no extra upstream calls.
        $agg = $this->featuredMatches([]);
        $matches = array_values(array_filter(
            $agg['matches'],
            fn (array $m): bool => (is_string($m['kickoff'] ?? null) ? substr($m['kickoff'], 0, 10) : '') === $date,
        ));

        return $this->aggregateEnvelope($this->sortByKickoff($matches), $agg);
    }

    /**
     * The next scheduled fixtures across featured competitions — so the app
     * surfaces what's coming (e.g. the World Cup) on quiet days rather than an
     * empty "today". Built from each competition's cached full-season feed.
     */
    public function upcoming(): JsonResponse
    {
        $today = Date::now()->toDateString();
        $agg = $this->featuredMatches([]);

        $upcoming = array_values(array_filter($agg['matches'], function (array $m) use ($today): bool {
            $day = is_string($m['kickoff'] ?? null) ? substr($m['kickoff'], 0, 10) : '';

            return ($m['status'] ?? null) === 'SCHEDULED' && $day >= $today;
        }));

        return $this->aggregateEnvelope(array_slice($this->sortByKickoff($upcoming), 0, 24), $agg);
    }

    /**
     * Fetch and merge each featured competition's matches for the given query.
     *
     * @param  array<string, string>  $query
     * @return array{matches: list<array<string, mixed>>, lastUpdated: string|null, stale: bool, served: bool}
     */
    private function featuredMatches(array $query): array
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

            // Read-only: never block the homepage on the rate-limited upstream —
            // serve cache/last-good and let app:warm-matches keep it fresh.
            $result = $this->football->cached("competition:{$code}:matches", $ttl, "/competitions/{$code}/matches", $query, refresh: false);

            if (is_array($result->data)) {
                $served = true;
                $matches = array_merge($matches, $this->normalizer->matches($result->data));
            }

            $stale = $stale || $result->stale;

            if ($result->lastUpdated !== null && ($lastUpdated === null || $result->lastUpdated > $lastUpdated)) {
                $lastUpdated = $result->lastUpdated;
            }
        }

        return ['matches' => $matches, 'lastUpdated' => $lastUpdated, 'stale' => $stale, 'served' => $served];
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function sortByKickoff(array $matches): array
    {
        $kickoff = static fn (array $m): string => is_string($m['kickoff'] ?? null) ? $m['kickoff'] : '';
        usort($matches, fn (array $a, array $b): int => strcmp($kickoff($a), $kickoff($b)));

        return $matches;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @param  array{lastUpdated: string|null, stale: bool, served: bool}  $agg
     */
    private function aggregateEnvelope(array $matches, array $agg): JsonResponse
    {
        return response()->json([
            'data' => $agg['served'] ? $matches : null,
            'meta' => ['lastUpdated' => $agg['lastUpdated'], 'stale' => $agg['stale'], 'cached' => true],
        ], $agg['served'] ? 200 : 503);
    }

    public function show(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("match:{$id}", Config::integer('football.ttl.match_live'), "/matches/{$id}"),
            $this->normalizer->match(...),
        );
    }
}
