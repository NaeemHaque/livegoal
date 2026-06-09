# LiveGoal — Architecture

One Laravel app serves **both** the JSON API and the built Vue SPA from a single origin. The Laravel
backend is the **only** thing that talks to football-data.org; the browser never calls the upstream API.

```
                          every 60s (1 cron)
 football-data.org  ◀──────────────────────────  PollLiveScores (scheduled command)
   (free, delayed)                                      │ writes
                                                        ▼
                                                Laravel cache (file / DB)
                                                        ▲ reads (cache-served)
                            GET /api/live (~15s)        │
 Browser (Vue SPA)  ─────────────────────────────────▶ Laravel /api/* endpoints
        ▲   same origin serves built SPA + JSON         │ on cache miss only ──▶ upstream
        └──────────────────────────────────────────────┘
```

## Why this shape

- **Hides the API key** — `FOOTBALL_DATA_TOKEN` lives only in `.env`, used server-side.
- **Protects the free 10 req/min limit at any traffic level** — `GET /matches?status=IN_PLAY,PAUSED`
  returns *all* live matches in **one** call, so the poller costs ~1 req/min for the whole site. Every
  user reads from cache.
- **No CORS** — SPA and API share one origin.
- **Cheapest hosting** — only PHP + a cron job. Runs on shared/cPanel hosting; no VPS, no persistent
  process, no websockets.

## Request flow

1. **First load** — browser hits any SPA route; `web.php` catch-all returns `app.blade.php`, which
   `@vite`s the built SPA. Vue Router (history mode) renders the page client-side.
2. **Data** — the SPA calls `/api/*` via `services/api.js` (axios). Controllers read from cache
   (`Cache::remember`), only hitting upstream on a miss.
3. **Live** — `useLiveMatches` polls `GET /api/live` every ~15s (paused when the tab is hidden). That
   endpoint is written by the poller, never fetched per request.

## Components

- **`Services/Football/FootballData`** — Laravel HTTP client (`Http::withHeaders(['X-Auth-Token' => …])`),
  base `https://api.football-data.org/v4`, retry/backoff on 429, returns last-good cache on failure,
  logs upstream errors. Never throws to the user.
- **`Services/Football/Normalizer`** — maps upstream JSON to LiveGoal DTO arrays (see
  [DATA_MODEL.md](./DATA_MODEL.md)), including status normalization and standings group parsing.
- **`Http/Controllers/Api/*`** — thin controllers; validate input, call the service (cache-served),
  return the JSON envelope (see [API.md](./API.md)).
- **`Console/Commands/PollLiveScores`** — the single poller (see [LIVE_POLLING.md](./LIVE_POLLING.md)).

## Caching & rate-limit math

Every `/api/*` reads cache and only hits upstream on a miss. Net upstream usage ≈ the poller
(~1 req/min) + rare cache misses → comfortably under 10 req/min at any traffic. Full TTL table in
[API.md](./API.md).

## Degradation

- **429 / upstream failure** → serve last-good cache + surface a small notice; never crash.
- **No live matches** → poller is a cheap no-op; `/api/live` returns an empty live set with `lastUpdated`.
- **Free-tier-absent data** (lineups/events/stats/player/squad) → intentional "Not available on this
  data plan" empty state, never blank.

## Honesty constraint

Free-tier scores are delayed a few minutes. Surface "updated Xs ago" near the LIVE badge (driven by the
poller's `lastUpdated`). Don't pretend to be faster than the data.

## Future-proofing (not wired)

The poller keeps one commented extension point — `// broadcast(new ScoreUpdated($m));` — so Laravel
Reverb can be added later on a VPS without refactoring. **v1 installs no broadcasting**: no
Pusher/Ably, no Reverb/Soketi, no Echo/socket.io, no SSE, no Redis/Horizon/queue workers
(`BUILD_PROMPT` §3).
