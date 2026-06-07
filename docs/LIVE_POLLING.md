# SocPlay — Live Polling & Goal Animation

"Realtime" is delivered by **polling**, which is appropriate because the free data source is itself
delayed and poll-only. One server poller feeds a cache that the whole site reads.

## Server: `PollLiveScores`

- **Schedule:** `Schedule::command('app:poll-live-scores')->everyMinute()` in `routes/console.php`.
- **Fetch:** `GET /matches?status=IN_PLAY,PAUSED` — **all** live matches in one request (~1 req/min total).
- **Diff:** compare each match to the previously cached version; track `priorHomeScore`/`priorAwayScore`
  per match so a goal is detectable downstream.
- **Write:** store the normalized live set in cache (`live:matches`, TTL ~70s) with a `lastUpdated`
  timestamp. `/api/live` serves this cache directly — never fetches per request.
- **No live matches:** cheap no-op; write an empty set + fresh `lastUpdated`.
- **Resilience:** on 429/failure, keep the last-good cache and log; never crash.
- **Extension point (commented, NOT wired):**

  ```php
  // foreach ($changed as $m) {
  //     // broadcast(new ScoreUpdated($m));  // future: Laravel Reverb on a VPS
  // }
  ```

### Cron

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

No cron on the host? Point free **cron-job.org** at a protected `/scheduler` route that runs
`schedule:run` (token-guarded). Documented in the deploy guide (Phase 6).

## Client: visibility-aware polling

- **`useLiveMatches`** polls `GET /api/live` every `settings.refresh` seconds (default 15) via axios.
- **Pause when hidden:** `useDocumentVisibility` stops the interval when the tab isn't visible; resume
  (and refetch once) on return. Implemented with `@vueuse/core` `useIntervalFn`.
- **Manual pause:** a settings toggle (`paused`) halts polling.
- **`RefreshIndicator`** shows a countdown ring to the next poll; "updated Xs ago" is derived from
  `meta.lastUpdated` and ticks every second.

## Goal detection & animation

1. Each poll's match list is diffed against the previous poll (and the server-provided prior score).
2. On a score increase for either side:
   - `ScoreDisplay`/`ScoreDigit` plays the flip animation (`pp-flip-in`).
   - `GoalToast` fires (`pp-goal-in`) with team + scorer + minute + new scoreline.
   - The ARIA-live region announces: *"Goal for {team}, {scorer}, {minute} minutes. Score now {x–y}."*
3. The goal animation works **even on secondary-data pages** because only the score needs to update;
   the absent timeline/lineups/stats underneath stay in their empty state.
4. Respect reduce-motion: when set, skip the toast/flip and just update the number.

## Why polling (not websockets) in v1

Free-tier data is delayed minutes and poll-only, so websockets would add cost/complexity for no
freshness gain. v1 uses **no** Pusher/Reverb/Echo/socket.io/SSE/Redis (`BUILD_PROMPT` §3). The single
commented `broadcast()` line is the only forward hook; Reverb-on-VPS is a documented future upgrade.

## Acceptance (Phase 3 / global)

- With live matches, `/api/live` reflects scores within ~60–75s and is served from cache (no upstream
  call per request); `lastUpdated` present.
- With no live matches, the poller is cheap/no-op.
- Total upstream usage ≈ poller only — under 10 req/min at any traffic.
- A goal triggers animation + toast + ARIA announce; polling pauses when the tab is hidden.
