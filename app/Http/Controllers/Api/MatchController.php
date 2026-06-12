<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\PollLiveScores;
use App\Services\Football\FeaturedMatches;
use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

class MatchController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
        private readonly FeaturedMatches $featured,
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
        $agg = $this->featured->all();

        return $this->aggregateEnvelope($this->featured->onDate($agg['matches'], $date), $agg);
    }

    /**
     * The next scheduled fixtures across featured competitions — so the app
     * surfaces what's coming (e.g. the World Cup) on quiet days rather than an
     * empty "today". Built from each competition's cached full-season feed.
     */
    public function upcoming(): JsonResponse
    {
        // A 60-day window rather than a count cap: long enough to carry a
        // whole tournament schedule (the WC's 104 fixtures) plus the other
        // featured leagues, without ever serving entire future seasons.
        $today = Date::now()->toDateString();
        $until = Date::now()->addDays(60)->toDateString();
        $agg = $this->featured->all();

        return $this->aggregateEnvelope($this->featured->scheduledWindow($agg['matches'], $today, $until), $agg);
    }

    /**
     * The latest results across the featured competitions — the Finished view
     * when no date is selected. A standard count, newest first.
     */
    public function results(): JsonResponse
    {
        $today = Date::now()->toDateString();
        $agg = $this->featured->all();

        return $this->aggregateEnvelope($this->featured->recentResults($agg['matches'], $today, 30), $agg);
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
            fn (array $payload): array => [
                ...$this->normalizer->match($payload),
                ...$this->finalSnapshot($id),
                'events' => $this->timelineEvents($id),
            ],
        );
    }

    /**
     * Status/score overrides from the poller's final snapshot, when this
     * match recently finished but the upstream record still misreports it.
     *
     * @return array<string, mixed>
     */
    private function finalSnapshot(string $id): array
    {
        $finals = Cache::get(PollLiveScores::FINALS_KEY);
        $final = is_array($finals) ? ($finals[$id] ?? null) : null;

        if (! is_array($final)) {
            return [];
        }

        return [
            'status' => 'FT',
            'minute' => null,
            'homeScore' => $final['homeScore'] ?? null,
            'awayScore' => $final['awayScore'] ?? null,
        ];
    }

    /**
     * Self-built timeline events recorded by the live poller (the free tier
     * has no event feed) — possibly empty, read straight from cache with no
     * upstream call.
     *
     * @return list<array<array-key, mixed>>
     */
    private function timelineEvents(string $id): array
    {
        $events = Cache::get(PollLiveScores::eventsKey($id));

        if (! is_array($events)) {
            return [];
        }

        return array_values(array_filter($events, is_array(...)));
    }
}
