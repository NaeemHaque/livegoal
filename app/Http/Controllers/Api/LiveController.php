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

        return $this->envelope(['matches' => $matches, 'count' => count($matches)], $result);
    }
}
