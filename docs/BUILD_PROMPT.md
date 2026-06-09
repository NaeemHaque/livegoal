# LiveGoal — Build Prompt for Claude Code

**LiveGoal — football (soccer) live-scores web app · Laravel API + Vue 3 SPA · free-tier data, polling-based, Production domain: livegoal.win (not purchased yet).**

This is the authoritative build spec. Read it fully (plus the Claude Design output) before writing any code. Build in the phases defined in §8, in order, with the commit plans given.

---

## 0. How to use this with Claude Code

1. Read this file alongside the exported Claude Design files.
2. First action: read this file + the design export, then output a short plan (folder structure, models, route table, polling strategy) before starting Phase 1.
3. Execute phase by phase. Do **not** start a phase until the previous phase's acceptance criteria pass.
4. Within a phase, make the listed commits (Conventional Commits), small and focused.
5. After each phase: build the frontend, run the app, verify acceptance, report status.
6. Only ask when genuinely blocked; otherwise proceed and state assumptions inline.
7. Create plan and refferenced md inside /docs

---

## 1. Goal & context

Build **LiveGoal** — a modern, responsive football live-scores web app — **World Cup 2026–aware** and covering major leagues (Premier League, Champions League, La Liga, Serie A, Bundesliga, Ligue 1, etc.). It shows live scores, fixtures, results, standings/groups, knockout brackets, competitions, and teams.

This is a **free, non-profit project**. Server cost must be bare minimum. There is **no budget for paid realtime infrastructure**. "Realtime" is delivered by polling (see §2), which is appropriate because the free data source is itself delayed and poll-only.

LiveGoal is a **pure, non-betting live-scores product** — no odds, tips, predictions-for-money, or affiliate/gambling content anywhere. (The `.win` TLD can read as betting; keep the product unambiguously a neutral scores tracker, which also keeps it within the data provider's terms.)

---

## 2. Architecture (the important part)

**One Laravel app serves both the JSON API and the Vue SPA. The Laravel backend is the single poller; the browser never calls the football API directly.**

```
                         every 60s (1 cron)
 football-data.org  ◀───────────────────────  Laravel scheduled command (PollLiveScores)
   (free, delayed)                                   │  writes
                                                      ▼
                                              Laravel cache (file/DB)
                                                      ▲  reads (cache-served)
                            GET /api/live (~15s)      │
 Browser (Vue SPA)  ───────────────────────────────▶ Laravel /api/* endpoints
        ▲  serves built SPA + JSON, same origin       │
        └───────────────────────────────────────────-┘
```

**Why this shape:**
- **Hides the API key** (server-side only).
- **Protects the free 10 req/min limit regardless of traffic.** `/matches?status=IN_PLAY,PAUSED` returns *all* live matches in **one** request, so polling every 60s costs ~1 req/min for the whole site, leaving headroom for on-demand cache-miss fetches. Every user reads from cache.
- **No CORS** — SPA and API share one origin.
- **Cheapest possible hosting** — needs only PHP + a cron job. Runs on shared/cPanel hosting; no VPS, no persistent process, no websockets.

**Honesty constraint:** free-tier scores are delayed a few minutes; surface an "updated Xs ago" timestamp near the LIVE badge. Don't pretend to be faster than the data.

---

## 3. Tech stack — use / do NOT use

### ✅ Use
- **Backend:** Laravel 11/12, PHP 8.2+, Laravel **HTTP client** (`Http` facade) for football-data.org, Laravel **Scheduler** (cron-driven), **file or database cache driver**, MySQL (SQLite acceptable for zero-config dev).
- **Frontend:** **Vue 3** (Composition API, `<script setup>`), **Vite**, **Vue Router** (history mode), **Pinia**, **Tailwind CSS** (latest), **@vueuse/core** (`useIntervalFn`, `useStorage`, `useDocumentVisibility`, `usePreferredDark`), **axios**, **lucide-vue-next** icons. Optional: `motion-v`/`@vueuse/motion` for score/goal animation; `vite-plugin-pwa` for installable/offline shell.
- **Delivery:** single Laravel app — Vue SPA in `resources/js`, built by Vite, served by a catch-all web route; API under `/api`.

### ❌ Do NOT use (v1)
- ❌ **Pusher / Ably** (paid SaaS).
- ❌ **Laravel Reverb / Soketi** (websocket servers — need a persistent process / VPS).
- ❌ **Laravel Echo / socket.io** — **no websockets at all in v1**.
- ❌ **Server-Sent Events (SSE)** in v1.
- ❌ **Redis / Horizon / always-on queue workers.**

> **Future-proofing only:** leave one clearly-commented extension point in the poller (e.g. `// broadcast(new ScoreUpdated($m));`) so Reverb can be added later on a VPS without refactoring. Do **not** install or wire any broadcasting now.

---

## 4. Data source: football-data.org v4

- **Base URL:** `https://api.football-data.org/v4`
- **Auth header:** `X-Auth-Token: <token>` — read from `FOOTBALL_DATA_TOKEN` env. Never hardcode.
- **Free competition codes:** `WC, CL, PL, PD, SA, BL1, FL1, DED, PPL, ELC, EC, BSA, CLI`.
- **Match statuses:** `SCHEDULED, TIMED, IN_PLAY, PAUSED, FINISHED, POSTPONED, SUSPENDED, CANCELLED`.
- **Endpoints used:**
    - `GET /competitions`
    - `GET /competitions/{id}`
    - `GET /competitions/{id}/standings` — parse groups (tournaments return multiple standings groups, e.g. World Cup A–L).
    - `GET /competitions/{id}/matches?dateFrom=&dateTo=&matchday=&status=&stage=`
    - `GET /competitions/{id}/scorers?limit=`
    - `GET /competitions/{id}/teams`
    - `GET /matches?status=IN_PLAY,PAUSED` — **live polling endpoint (all live in one call)**; also `?dateFrom=&dateTo=&competitions=&ids=`
    - `GET /matches/{id}`
    - `GET /teams/{id}` (squad included)
    - `GET /teams/{id}/matches?status=&dateFrom=&dateTo=`
    - `GET /persons/{id}` (player)
- **Head-to-head:** derive from both teams' recent matches (`/teams/{id}/matches`) — robust on free tier.
- **Crest & flag images:** the API returns public asset URLs — load them **directly in `<img>` from the browser** (no API call, no rate-limit cost).
- **Free-tier limits to respect:** 10 requests/minute; delayed scores; **no lineups, no match events, no detailed match stats, no detailed player stats** (see §5). Handle HTTP **429** with backoff + serve last-good cache; never crash.

---

## 5. Screen tiering (free-tier data reality)

Design and prioritize around what the free tier actually returns.

**PRIMARY — fully populated, the strongest part of the product:**
Live hub · fixtures · results/scores · standings + World Cup group cards · knockout bracket · competition pages · team overview/fixtures/results · Match Detail header (score, status, minute, venue, date) + head-to-head + mini-standings.

**SECONDARY — empty is the *normal* state; design them to look intentional and finished when empty, not as error states:**
Match events timeline · lineups/formation pitch · detailed match stats (possession/shots/corners) · player profiles · squads.

**Rules:**
- A live Match Detail page with no lineup/stat/event data must still feel complete with just the **score header + head-to-head + mini-standings**.
- The **goal animation still works** on secondary-data pages because the **score itself updates** (scoreboard flips, GoalToast fires); only the timeline detail underneath is absent.
- Secondary panels render a clean "Not available on this data plan" state by default — never a blank or a crash.
- Add a small **"updated Xs ago"** timestamp near the LIVE badge (driven by the poller's `lastUpdated`).

---

## 6. Caching & rate-limit strategy

Every `/api/*` endpoint reads from cache and only hits upstream on a miss (`Cache::remember`). Live data is written by the poller, not fetched per request.

| Data | Source | TTL |
|---|---|---|
| `/api/live` (live matches) | written by `PollLiveScores` | ~70s |
| Match detail (live) | poller / on-demand | 30–60s |
| Match detail (finished) | on-demand | 10 min |
| Standings / groups | on-demand | 5–10 min |
| Fixtures / results lists | on-demand | 5–10 min |
| Top scorers | on-demand | 30 min |
| Competitions list | on-demand | 24 h |
| Team / player detail | on-demand | 12–24 h |
| Crests / flags | direct browser `<img>` | browser cache |

Net upstream usage ≈ the poller (~1 req/min) + rare cache misses → comfortably under 10 req/min at any traffic level.

---

## 7. Working procedure (how Claude Code should operate)

- **Plan-first:** before Phase 1, output folder structure, model/DTO shapes, route table, and the polling + score-diff plan. Surface any data assumptions.
- **Phase gating:** complete phases in order; don't begin a phase until the prior phase's acceptance criteria pass.
- **Commits:** Conventional Commits (`feat:`, `fix:`, `chore:`, `refactor:`, `docs:`, `perf:`, `build:`), one logical change each, following the per-phase commit plan.
- **Verify each phase:** run `php artisan serve` + `npm run dev` (or `npm run build`), exercise the acceptance checklist, report pass/fail.
- **Secrets:** keep out of git; provide `.env.example`.
- **No excluded tools** (§3). Keep only the commented broadcast extension point.
- **Docs:** update `README.md` continuously (setup, env, free-tier caveats, deploy, upgrade path).

---

## 8. Implementation phases

Six phases. Each contains multiple commits.

### Phase 1 — Foundation & tooling
**Goal:** A running Laravel app serving a themed Vue 3 SPA shell with routing, matching the Claude Design tokens.
**Deliverables:** Laravel installed; Vue 3 + Vite + Tailwind + Pinia + Router wired into Laravel; design tokens (color/type/spacing/radius/motion) in `tailwind.config`; dark/light theme toggle (persisted, seeded by `usePreferredDark`); app shell (top bar with the **LiveGoal** wordmark/logo + search slot + theme toggle; desktop sidebar nav; mobile bottom tab bar: Live/Matches/Competitions/Favorites/More; sticky live-ticker slot); routed page stubs + 404; `.env.example` with `FOOTBALL_DATA_TOKEN`.
**Commit plan:**
1. `chore: scaffold Laravel app + base config`
2. `build: add Vite, Vue 3, Tailwind, Pinia, Vue Router`
3. `feat: design tokens + dark/light theme`
4. `feat: app shell (LiveGoal wordmark, sidebar, mobile tab bar, live-ticker slot)`
5. `feat: route table with page stubs + 404`
6. `chore: .env.example + README quickstart`
   **Acceptance:** app boots; LiveGoal wordmark shows in the shell; theme toggles and persists; every route navigates to a stub; shell is responsive at mobile (bottom tab bar) and desktop (sidebar) widths.

### Phase 2 — Backend: FootballData service + cached read API
**Goal:** All on-demand data exposed via cached internal JSON endpoints; no per-user upstream calls beyond cache misses.
**Deliverables:** `FootballData` service (Laravel HTTP client, `X-Auth-Token` from env, base v4, normalizes responses, handles 429 with backoff + logging, serves last-good on failure); API controllers + `/api` routes for competitions, competition detail, standings (with group parsing), matches (by date / by competition), match detail, teams, team matches, scorers, person; `Cache::remember` per the TTL table; consistent JSON envelope; crest/flag URLs passed through for direct browser loading.
**Commit plan:**
1. `feat(api): FootballData service (HTTP client, auth, 429/backoff)`
2. `feat(api): competitions + competition detail endpoints`
3. `feat(api): standings endpoint with group parsing`
4. `feat(api): matches (by date/competition) + match detail endpoints`
5. `feat(api): teams, team matches, scorers, person endpoints`
6. `refactor(api): response normalizer + TTL config + JSON envelope`
   **Acceptance:** each `/api/*` returns normalized JSON; repeated calls hit cache (verify upstream call count); 429 returns cached/last-good without crashing; World Cup standings parse into groups.

### Phase 3 — Backend: live polling engine
**Goal:** A single scheduled poller keeps live scores fresh in cache for the whole site.
**Deliverables:** `PollLiveScores` command (`GET /matches?status=IN_PLAY,PAUSED` → diff vs cached → store; track prior score per match; set `lastUpdated`; clearly-commented broadcast extension point, **not** wired); scheduler `everyMinute`; `/api/live` endpoint (cache-served, includes `lastUpdated` + live flags); cron docs + cron-job.org fallback.
**Commit plan:**
1. `feat(live): PollLiveScores command (IN_PLAY + PAUSED)`
2. `feat(live): score-diff + lastUpdated + cache write (70s TTL)`
3. `feat(live): /api/live endpoint (cache-served) with lastUpdated`
4. `chore(live): schedule everyMinute + document cron / cron-job.org`
5. `docs(live): commented broadcast extension point + upgrade note`
   **Acceptance:** with live matches, `/api/live` reflects scores within ~60–75s and is served from cache (no upstream call per request); with no live matches the poller is cheap/no-op; `lastUpdated` present; total upstream ≈ poller only.

### Phase 4 — Frontend core (data, polling, states)
**Goal:** Frontend infrastructure ready for screens — API client, stores, composables, visibility-aware polling, refresh indicator, global states.
**Deliverables:** `services/api.js` (axios → `/api`); Pinia stores (`matches`, `favorites` via `useStorage`/localStorage, `settings`: theme/timezone/refresh interval); composables (`useLiveMatches` polling `/api/live` ~15s, **paused when tab hidden** via `useDocumentVisibility`; `useMatch`, `useStandings`, `useCompetitions`, `useCompetition`, `useTeam`, `useScorers`); `RefreshIndicator` (countdown ring) + **"updated Xs ago"** derived from `lastUpdated`; `Skeleton`, `EmptyState`, `ErrorState`, `OfflineState` components; timezone-aware time formatting; favorites/settings persistence.
**Commit plan:**
1. `feat(fe): api client + Pinia stores (matches/favorites/settings)`
2. `feat(fe): data composables (live/match/standings/competition/team/scorers)`
3. `feat(fe): visibility-aware live polling + RefreshIndicator + "updated Xs ago"`
4. `feat(fe): global state components (skeleton/empty/error/offline)`
5. `feat(fe): timezone time formatting + settings persistence`
   **Acceptance:** live composable polls only when tab visible and pauses when hidden; "updated Xs ago" ticks; loading shows skeletons; failed fetch shows error + retry; favorites/settings survive reload.

### Phase 5 — Primary screens (fully populated)
**Goal:** Build the screens with real free-tier data, to design fidelity, with live updates + goal animation.
**Deliverables (PRIMARY tier):**
- **Live Hub:** live-now hero cards (pulsing minute, animated score), today's fixtures grouped by competition, sticky live ticker, quick status filters, World Cup spotlight.
- **Matches/Fixtures:** date navigator (prev/Today/next + calendar), grouped by competition, status filters (Live/Upcoming/Finished), favorites pinned.
- **Results/Scores:** finished matches by date/competition.
- **Standings + World Cup group cards:** full table (Pos, crest, P, W, D, L, GF, GA, GD, Pts) with W/D/L form pills + qualification-zone coloring; all 12 groups as cards.
- **Knockout bracket:** Round of 32 → Final, scrollable.
- **Competition Detail:** tabs — Standings/Groups, Bracket, Fixtures, Results, Top Scorers, Teams.
- **Team Detail:** overview (next/last matches + form), fixtures, results, standings.
- **Match Detail (complete-when-empty):** big score header + status/minute + venue/date; head-to-head (derived); mini-standings; **goal animation** (score flip/count) + **GoalToast** + ARIA live announce on score change; "updated Xs ago" near LIVE badge.
  **Commit plan:**
1. `feat(screen): Live Hub + live ticker + goal animation/toast`
2. `feat(screen): Matches/Fixtures with date nav + filters`
3. `feat(screen): Results/Scores`
4. `feat(screen): Standings + World Cup group cards`
5. `feat(screen): Knockout bracket`
6. `feat(screen): Competition Detail (tabbed)`
7. `feat(screen): Team Detail (overview/fixtures/results)`
8. `feat(screen): Match Detail (score header + H2H + mini-standings)`
   **Acceptance:** all primary screens match design in dark + light, responsive; Live Hub + Match header update via polling with no manual refresh; a goal triggers animation + toast + ARIA announce; World Cup shows 12 groups + bracket; H2H and mini-standings populate; no blank/crash where free data is absent.

### Phase 6 — Secondary screens, polish & ship
**Goal:** Add data-poor screens as intentional "complete-when-empty" designs, finish supporting features, optimize, document deploy.
**Deliverables (SECONDARY tier + polish):**
- **Match Detail extra tabs:** Events timeline, Lineups/formation pitch, Detailed stats — each a clean "Not available on this data plan" state by default; populated only if data present.
- **Player Detail + Team Squad:** same graceful-empty treatment.
- **Favorites** page (star teams/competitions, surface their live/upcoming), **Search** (competitions/teams/players + recent searches), **Settings** (theme/timezone/refresh interval).
- **Polish:** route transitions, lazy-loaded crest/flag images, long-list virtualization, debounced search, `prefers-reduced-motion`, WCAG AA contrast, keyboard nav, ARIA live region for score updates.
- **Optional:** PWA (installable + offline shell) via `vite-plugin-pwa`.
- **Deploy docs:** `npm run build`; single-host deploy (cPanel/shared) at **livegoal.win** — point the domain at the host, enable free HTTPS (Let's Encrypt / host SSL or Cloudflare in front), set `APP_URL=https://livegoal.win`; cron entry; `.env`; SPA fallback route; cron-job.org fallback; future Reverb-on-VPS upgrade path. Keep LiveGoal a non-betting scores product (no odds/tips/affiliate content).
  **Commit plan:**
1. `feat(screen): Match Detail secondary tabs as graceful-empty`
2. `feat(screen): Player Detail + Team Squad (graceful-empty)`
3. `feat(screen): Favorites + Search + Settings`
4. `perf+a11y: lazy images, virtualization, transitions, reduced-motion, ARIA, keyboard nav`
5. `feat(pwa): installable + offline shell` *(optional)*
6. `docs(deploy): README deploy guide (livegoal.win) + cron + env + upgrade path`
   **Acceptance:** secondary screens look finished when empty (never blank/broken); favorites/search/settings work; reasonable Lighthouse a11y; build deploys to a single host at livegoal.win with HTTPS, a working cron poller, and SPA routing; README complete.

---

## 9. Global acceptance criteria

- App runs as a **single Laravel host** serving the Vue SPA + `/api` at **livegoal.win**; no separate frontend host; no CORS.
- API token lives only in `.env` (server-side); never shipped to the browser.
- Live scores update on screen without manual refresh; a goal animates + toasts; "updated Xs ago" reflects `lastUpdated`.
- Upstream usage stays under **10 req/min** at any traffic level (central poller + cache verified).
- 429 / upstream failure degrades gracefully (last-good cache, visible notice), never crashes.
- Every page matches the approved design in **dark and light**, fully responsive (mobile bottom tab bar; desktop sidebar).
- Free-tier-absent data (lineups/events/stats/player/squad) renders the intentional empty state, never blank or broken.
- LiveGoal stays a neutral, **non-betting** live-scores product (no odds/tips/affiliate content).
- **No** Pusher, Reverb, Echo, socket.io, SSE, or Redis anywhere; only the commented broadcast extension point remains.

## 10. Definition of Done

Frontend builds clean (`npm run build`); ESLint/Prettier clean; backend boots with a real `FOOTBALL_DATA_TOKEN`; cron poller documented and working; favorites/settings persist; README documents setup, env, the free-tier delayed-score caveat, the single-host deploy at **livegoal.win** (domain pointed, HTTPS on, `APP_URL` set) + cron (and cron-job.org fallback), and the future websocket upgrade path. Deployable to a host costing ~$0–$2/mo.

---

## Appendix A — Repo structure (single app)

```
app/
  Console/Commands/PollLiveScores.php
  Services/Football/FootballData.php
  Http/Controllers/Api/{LiveController,CompetitionController,MatchController,
                        TeamController,PlayerController,ScorerController}.php
routes/
  api.php          # /api/* JSON endpoints
  console.php      # Schedule::command('app:poll-live-scores')->everyMinute()
  web.php          # catch-all → SPA shell
resources/
  js/
    main.js  App.vue
    router/index.js
    stores/{matches,favorites,settings}.js
    composables/{useLiveMatches,useMatch,useStandings,useCompetitions,
                 useCompetition,useTeam,useScorers}.js
    services/api.js
    components/{MatchCard,LiveBadge,ScoreDisplay,MatchStatus,TeamChip,CountryFlag,
                StandingsTable,FormGuide,GroupCard,Bracket,TimelineEvent,
                StatBar,H2H,DateNavigator,FilterTabs,FavoriteStar,GoalToast,
                RefreshIndicator}.vue
    components/states/{Skeleton,EmptyState,ErrorState,OfflineState}.vue
    pages/{LiveHub,Matches,MatchDetail,Competitions,CompetitionDetail,TeamDetail,
           PlayerDetail,Scorers,Favorites,Search,Settings,NotFound}.vue
  css/app.css
.env.example       # FOOTBALL_DATA_TOKEN=, CACHE_STORE=file (or database), APP_URL=https://livegoal.win
```

## Appendix B — Cron entry

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```
No cron on your host? Point free **cron-job.org** at a protected `/scheduler` route that calls `schedule:run`.

## Appendix C — Domain & go-live (livegoal.win is not accessable yet so take decisiton when need to do this. now or after purchase)

- Point `livegoal.win` (A / ALIAS, or CNAME) at the single host serving the Laravel app. The SPA and `/api` share this one origin — no second host, no CORS.
- Enable HTTPS (free Let's Encrypt via the host, or put Cloudflare in front). Force `https://` and pick one canonical host (apex `livegoal.win` or `www`), redirecting the other.
- Set `APP_URL=https://livegoal.win` in `.env`; make sure Vue Router (history mode) has the SPA fallback route so deep links resolve.
- Branding: the in-app wordmark/logo reads **LiveGoal**; page `<title>` and meta default to "LiveGoal — Live Football Scores".
- Keep it a pure, **non-betting** live-scores product (no odds, tips, or affiliate content) — the `.win` TLD reads as betting, and football-data.org's terms disfavor gambling use, so stay unambiguously a neutral scores tracker.
