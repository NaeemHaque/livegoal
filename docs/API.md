# LiveGoal — Internal JSON API

All endpoints live under `/api`, are **cache-served**, and return a consistent envelope. The browser
talks only to these; never to football-data.org. Upstream codes & endpoints: `BUILD_PROMPT` §4.

## Response envelope

```jsonc
{
  "data": <payload>,           // normalized DTO or array (see DATA_MODEL.md)
  "meta": {
    "lastUpdated": "2026-06-07T18:00:00Z", // when the cached payload was produced
    "stale": false,            // true when served as last-good after an upstream failure
    "cached": true             // served from cache (vs fresh upstream fill)
  }
}
```

Errors never 500 to the user: on upstream failure the endpoint returns the last-good payload with
`meta.stale = true`. A hard miss with no cache returns `{ "data": null|[], "meta": { "stale": true } }`
plus an appropriate status, and the SPA shows `ErrorState`/`OfflineState`.

## Endpoints

| Method & path | Purpose | Upstream (on miss) | TTL |
|---|---|---|---|
| `GET /api/live` | All live matches + `lastUpdated` | written by poller | ~70s |
| `GET /api/competitions` | Competitions list | `/competitions` (filtered to free codes) | 24 h |
| `GET /api/competitions/{id}` | Competition detail | `/competitions/{id}` | 24 h |
| `GET /api/competitions/{id}/standings` | Standings, **parsed into groups** | `/competitions/{id}/standings` | 5–10 min |
| `GET /api/competitions/{id}/matches` | Fixtures/results (`?matchday=&status=&stage=&dateFrom=&dateTo=`) | `/competitions/{id}/matches` | 5–10 min |
| `GET /api/competitions/{id}/scorers` | Top scorers (`?limit=`) | `/competitions/{id}/scorers` | 30 min |
| `GET /api/competitions/{id}/teams` | Teams in competition | `/competitions/{id}/teams` | 12–24 h |
| `GET /api/matches` | Matches by `?date=&competition=&status=` | `/matches?dateFrom=&dateTo=&competitions=` | 5–10 min |
| `GET /api/matches/{id}` | Match detail | `/matches/{id}` | live 30–60s · finished 10 min |
| `GET /api/teams/{id}` | Team detail (+ squad) | `/teams/{id}` | 12–24 h |
| `GET /api/teams/{id}/matches` | Team fixtures/results (`?status=&dateFrom=&dateTo=`) | `/teams/{id}/matches` | 5–10 min |
| `GET /api/persons/{id}` | Player profile | `/persons/{id}` | 12–24 h |

Crest & flag URLs are passed straight through in payloads and loaded directly by the browser `<img>`
(no API call, no rate-limit cost).

## Free competition codes

`WC, CL, PL, PD, SA, BL1, FL1, DED, PPL, ELC, EC, BSA, CLI` — map to LiveGoal ids in
[DATA_MODEL.md](./DATA_MODEL.md). Resolve numeric upstream competition ids from these codes.

## Caching rules

- One `Cache::remember($key, $ttl, …)` per endpoint; key includes all query params.
- `/api/live` is **written by the poller**, not filled per request (see [LIVE_POLLING.md](./LIVE_POLLING.md)).
- Head-to-head is **derived** from both teams' recent `/teams/{id}/matches` (robust on free tier).
- TTLs come from `config/football.php` so they're tunable without code changes.

## 429 / backoff

The `FootballData` service handles HTTP 429 with backoff + logging and returns last-good cache. The poller's
1 req/min plus rare cache misses keep usage under the 10 req/min ceiling; 429 should be rare and never
user-visible beyond a "showing last update" note.

## H2H derivation

`H2H(teamA, teamB)` = intersect recent matches from `/teams/A/matches` and `/teams/B/matches` where the
opponent matches, sorted by date desc, limited to last N meetings. Cached under a composite key.
