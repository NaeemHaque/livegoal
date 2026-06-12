<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\PollLiveScores;
use App\Services\Football\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LiveController extends Controller
{
    /**
     * Serve the live match set written by PollLiveScores — always from cache,
     * never an upstream call. `stale` is true only before the poller's first run.
     */
    public function index(): JsonResponse
    {
        $payload = Cache::get(PollLiveScores::CACHE_KEY);

        $matches = is_array($payload) && is_array($payload['matches'] ?? null)
            ? array_values($payload['matches'])
            : [];

        $lastUpdated = is_array($payload) ? ($payload['lastUpdated'] ?? null) : null;

        $result = new Result(
            data: $matches,
            stale: $payload === null,
            cached: true,
            lastUpdated: is_string($lastUpdated) ? $lastUpdated : null,
        );

        // Recently finished matches (self-detected; the upstream erases them
        // at the final whistle) — lets the frontend show FT scores in lists.
        $finals = Cache::get(PollLiveScores::FINALS_KEY);
        $finals = is_array($finals) ? array_values(array_filter($finals, is_array(...))) : [];

        return $this->envelope(['matches' => $matches, 'count' => count($matches), 'finals' => $finals], $result);
    }
}
