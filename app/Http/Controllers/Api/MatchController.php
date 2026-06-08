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
        $query = ['dateFrom' => $date, 'dateTo' => $date];
        $ttl = Config::integer('football.ttl.matches');

        $matches = [];
        $lastUpdated = null;
        $stale = false;
        $served = false;

        foreach (Config::array('football.featured') as $code) {
            if (! is_string($code)) {
                continue;
            }

            $result = $this->football->cached("competition:{$code}:matches", $ttl, "/competitions/{$code}/matches", $query);

            if (is_array($result->data)) {
                $served = true;
                $matches = array_merge($matches, $this->normalizer->matches($result->data));
            }

            $stale = $stale || $result->stale;

            if ($result->lastUpdated !== null && ($lastUpdated === null || $result->lastUpdated > $lastUpdated)) {
                $lastUpdated = $result->lastUpdated;
            }
        }

        $kickoff = static fn (array $m): string => is_string($m['kickoff'] ?? null) ? $m['kickoff'] : '';
        usort($matches, fn (array $a, array $b): int => strcmp($kickoff($a), $kickoff($b)));

        return response()->json([
            'data' => $served ? $matches : null,
            'meta' => ['lastUpdated' => $lastUpdated, 'stale' => $stale, 'cached' => true],
        ], $served ? 200 : 503);
    }

    public function show(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("match:{$id}", Config::integer('football.ttl.match_live'), "/matches/{$id}"),
            $this->normalizer->match(...),
        );
    }
}
