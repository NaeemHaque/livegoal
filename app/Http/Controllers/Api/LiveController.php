<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\HarvestLiveEspn;
use App\Console\Commands\PollLiveScores;
use App\Services\Football\EspnLiveOverlay;
use App\Services\Football\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LiveController extends Controller
{
    public function __construct(private readonly EspnLiveOverlay $espnOverlay) {}

    /**
     * Serve the live match set written by PollLiveScores — always from cache,
     * never an upstream call. `stale` is true only before the poller's first run.
     */
    public function index(): JsonResponse
    {
        $payload = Cache::get(PollLiveScores::CACHE_KEY);

        $matches = is_array($payload) && is_array($payload['matches'] ?? null)
            ? array_values(array_filter($payload['matches'], is_array(...)))
            : [];

        // Freshen status/minute/score from ESPN's near-real-time overlay (written
        // by the app:harvest-live-espn schedule) so the home "Live now" rail keeps
        // pace with the match page. Pure cache merge — no wire call, so this
        // endpoint stays strictly cache-served.
        $espnMap = Cache::get(HarvestLiveEspn::OVERLAY_KEY);
        $matches = $this->espnOverlay->overlay($matches, is_array($espnMap) ? $espnMap : []);

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
