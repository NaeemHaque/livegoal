# LiveGoal — Implementation Plan

> Master plan for building **LiveGoal**, a free, non-betting football live-scores web app.
> Authoritative spec: [`BUILD_PROMPT.md`](./BUILD_PROMPT.md). Design source: [`design-ref/`](./design-ref/).
> This file is the plan-first deliverable required by `BUILD_PROMPT` §0.2 / §7.

## Referenced docs

| Doc | What it covers |
|---|---|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | The single-app SPA + JSON API shape, data flow, rate-limit math, extension points |
| [API.md](./API.md) | `/api/*` endpoint catalog, JSON envelope, cache TTLs, 429 handling, upstream mapping |
| [DATA_MODEL.md](./DATA_MODEL.md) | Normalized DTO shapes + football-data.org → LiveGoal field/status mapping |
| [DESIGN.md](./DESIGN.md) | Design tokens, theming, fonts, component & screen inventory, motion, a11y |
| [LIVE_POLLING.md](./LIVE_POLLING.md) | The poller, score-diff, `lastUpdated`, visibility-aware client polling, goal animation |
| [PR_PLAN.md](./PR_PLAN.md) | Branching model + per-phase branch/PR workflow targeting `dev` |
| [DECISIONS.md](./DECISIONS.md) | Decision log (architecture, language, nav, naming) with rationale |

---

## 1. Confirmed decisions (see [DECISIONS.md](./DECISIONS.md))

- **Architecture:** decoupled **Vue 3 SPA + Laravel JSON API**, one app, one origin. **Inertia + Wayfinder are removed.**
- **Frontend language:** **plain JavaScript** (no TypeScript). `vue-tsc` is dropped from the gate; ESLint switches to a Vue + JS config.
- **Navigation:** **top horizontal nav** (Sofascore-style) on desktop + **mobile bottom tab bar**. The build prompt's "sidebar" text is superseded by the final design (the chat transcript settles this).
- **CSS namespace:** keep the design's `pp-` class prefix so `tokens.css` / `components.css` port 1:1 (the design predates the LiveGoal rename; classnames are internal).
- **Data source:** football-data.org v4, token server-side only. The mock `data.js` in the design defines the shapes our normalizer targets.

---

## 2. Target repo structure

```
app/
  Console/Commands/PollLiveScores.php          # single scheduled poller
  Services/Football/FootballData.php           # HTTP client, auth, 429/backoff, normalize
  Services/Football/Normalizer.php             # upstream JSON -> LiveGoal DTO arrays
  Http/Controllers/Api/
    LiveController.php  CompetitionController.php  MatchController.php
    TeamController.php  PlayerController.php       ScorerController.php
  Http/Resources/ (optional)                   # JSON envelope helpers if useful
routes/
  api.php          # /api/* JSON endpoints
  console.php      # Schedule::command('app:poll-live-scores')->everyMinute()
  web.php          # catch-all -> SPA shell (Blade)
config/
  football.php     # base url, token, competition codes, cache TTLs
resources/
  js/
    main.js  App.vue
    router/index.js                            # Vue Router, history mode + scroll
    stores/{matches,favorites,settings}.js     # Pinia
    services/api.js                            # axios -> /api
    composables/{useLiveMatches,useMatch,useStandings,useCompetitions,
                 useCompetition,useTeam,useScorers}.js
    components/{MatchCard,LiveBadge,ScoreDisplay,MatchStatus,MinuteTicker,TeamChip,
                Crest,CountryFlag,StandingsTable,FormGuide,GroupCard,Bracket,BracketNode,
                TimelineEvent,StatComparisonBar,PossessionBar,H2HWidget,DateNavigator,
                CalendarPopover,FilterTabs,FavoriteStar,GoalToast,RefreshIndicator,
                Pitch,FormationView,Logo,TopNav,LiveTicker,TabBar,SearchModal}.vue
    components/states/{Skeleton,EmptyState,ErrorState,OfflineState}.vue
    pages/{LiveHub,Matches,MatchDetail,Competitions,CompetitionDetail,TeamDetail,
           PlayerDetail,Scorers,Favorites,Search,Settings,NotFound}.vue
  css/{tokens.css,components.css,app.css}      # ported from design-ref
  views/app.blade.php                          # mounts #app, @vite
.env.example      # FOOTBALL_DATA_TOKEN=, CACHE_STORE=file, APP_URL=https://livegoal.win
```

## 3. Route table

### SPA (Vue Router, history mode)

| Path | Page | Notes |
|---|---|---|
| `/` | LiveHub | live-now hero, today's fixtures, ticker, WC spotlight |
| `/matches` | Matches | date navigator + status filters |
| `/match/:id` | MatchDetail | score header + H2H + mini-standings (+ secondary tabs) |
| `/competitions` | Competitions | grid of competitions |
| `/competition/:id` | CompetitionDetail | tabs: Standings/Groups · Bracket · Fixtures · Results · Scorers · Teams |
| `/team/:id` | TeamDetail | overview/fixtures/results/standings (+ squad) |
| `/player/:id` | PlayerDetail | graceful-empty |
| `/scorers` | Scorers | top scorers |
| `/favorites` | Favorites | followed teams/comps |
| `/search` | Search | (also a `⌘K` / `/` modal) |
| `/settings` | Settings | theme/timezone/refresh interval |
| `*` | NotFound | 404 |

### JSON API (`routes/api.php`) — all cache-served (see [API.md](./API.md))

```
GET /api/live
GET /api/competitions
GET /api/competitions/{id}
GET /api/competitions/{id}/standings
GET /api/competitions/{id}/matches
GET /api/competitions/{id}/scorers
GET /api/competitions/{id}/teams
GET /api/matches            ?date=&competition=&status=
GET /api/matches/{id}
GET /api/teams/{id}
GET /api/teams/{id}/matches
GET /api/persons/{id}
```

## 4. Data model (summary — full shapes in [DATA_MODEL.md](./DATA_MODEL.md))

Normalized DTOs returned by the API and consumed by the SPA:
`Competition`, `Team`, `Match` (status normalized to `SCHEDULED|LIVE|HT|FT|ET|PEN|POSTPONED`),
`StandingGroup` + `StandingRow`, `Scorer`, `Person`. Crest/flag URLs are passed through for direct
browser `<img>` loading (no API cost).

## 5. Polling & live strategy (full detail in [LIVE_POLLING.md](./LIVE_POLLING.md))

- **Server:** `PollLiveScores` runs `everyMinute`, calls `GET /matches?status=IN_PLAY,PAUSED` (all live in one request), diffs vs cached, writes `/api/live` cache (~70s TTL) with `lastUpdated` and per-match prior score.
- **Client:** `useLiveMatches` polls `GET /api/live` every ~15s via axios, **paused when the tab is hidden** (`useDocumentVisibility`). A `RefreshIndicator` ring counts down; "updated Xs ago" derives from `lastUpdated`.
- **Goal animation:** score change → `ScoreDisplay` flip + `GoalToast` + ARIA-live announce. Works even where secondary data is absent because the score itself updates.
- **Extension point:** one commented `// broadcast(new ScoreUpdated($m));` line in the poller; nothing broadcasting installed.

## 6. Design system → build (full detail in [DESIGN.md](./DESIGN.md))

Port `design-ref/project/tokens.css` and `components.css` into `resources/css`. Theme via
`data-theme="dark|light"` on `<html>` (default dark "Stadium Night"; light "Daylight"), persisted and
seeded by `usePreferredDark`. Tailwind v4 `@theme` maps the CSS variables so utilities and the token
variables coexist.

---

## 7. Phase plan

Build in the six phases from `BUILD_PROMPT` §8, in order, gating on each phase's acceptance criteria.
Commits follow Conventional Commits per the build prompt's per-phase commit plans.

| Phase | Goal | Gate |
|---|---|---|
| **0. Convert scaffold** | Remove Inertia/Wayfinder/TS; wire Vue Router + Pinia + axios + plain JS; port design tokens; update CLAUDE.md, agents, ESLint, CI (drop `vue-tsc`). | App boots as a vanilla Vue SPA shell; lint/build clean. |
| **1. Foundation & tooling** | Themed SPA shell: top nav, mobile tab bar, live-ticker slot, theme toggle, routed page stubs + 404, `.env.example`. | Wordmark shows; theme toggles & persists; every route navigates; responsive. |
| **2. Backend API** | `FootballData` service + cached `/api/*` read endpoints (429/backoff, last-good, group parsing). | Each `/api/*` returns normalized JSON; repeat calls hit cache; 429 degrades; WC standings parse to groups. |
| **3. Live polling** | `PollLiveScores` + `/api/live` + scheduler + cron docs. | `/api/live` fresh within ~60–75s, cache-served; `lastUpdated` present; no-op when no live. |
| **4. Frontend core** | api client, stores, composables, visibility-aware polling, RefreshIndicator, global states, tz formatting. | Polls only when visible; "updated Xs ago" ticks; skeleton/empty/error/offline; favorites/settings persist. |
| **5. Primary screens** | LiveHub, Matches, Results, Standings+groups, Bracket, CompetitionDetail, TeamDetail, MatchDetail. | Match design in dark+light, responsive; live updates + goal animation; 12 WC groups + bracket; complete-when-empty. |
| **6. Secondary + ship** | Match secondary tabs, Player/Squad, Favorites/Search/Settings, perf+a11y, optional PWA, deploy docs. | Empty states look finished; a11y solid; single-host deploy + cron + SPA fallback; README complete. |

> **Phase 0** is added because the repo starts as an Inertia/TS scaffold; it must become a vanilla-JS Vue SPA before `BUILD_PROMPT` Phase 1. It also reconciles the AI workflow assets (CLAUDE.md, agents, skills) that currently assume Inertia/TS.

## 8. Assumptions & open items

- **Domain `livegoal.win` not purchased yet** — build host-agnostic; set `APP_URL` later (build prompt App. C). No go-live steps until purchase.
- **`FOOTBALL_DATA_TOKEN`** needed for live backend verification (Phases 2–3). Until provided, verify with cached fixtures / the design's mock shapes and document the gap.
- **Club crests are trademarked** — the design uses colored monogram badges for clubs and real flags (flagcdn) for nations. football-data.org returns crest URLs; we load them directly in `<img>`. We mirror the design's monogram fallback when a crest is missing.
- **football-data.org free tier** lacks lineups/events/detailed stats/player stats → those screens are the intentional "Not available on this data plan" empty state (secondary tier).
- **Testing:** keep PHPUnit feature tests for the API/service/poller (cache hits, 429 fallback, group parsing, score-diff). Frontend stays lint+build-gated (no JS unit framework added unless requested).
