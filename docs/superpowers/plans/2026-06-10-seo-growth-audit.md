# LiveGoal — SEO, Growth & Content Audit + Changes Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement the Changes Plan (Part B) task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make LiveGoal (client-rendered Vue SPA + cached JSON API) discoverable, indexable, and rich-result-eligible for search engines and AI answer engines, without abandoning the SPA architecture.

**Architecture:** Server-side "SEO shell" — the existing `Route::fallback` blade response becomes route-aware and injects per-URL title/description/canonical/Open Graph/JSON-LD from the already-cached football data; plus sitemap, correct HTTP status codes, and slug URLs. No SSR framework is introduced.

**Tech Stack:** Laravel 13, Vue 3 + Vue Router 5 + Pinia (plain JS), Tailwind v4, SQLite cache, football-data.org free tier.

**Date:** 2026-06-10 (one day before the 2026 World Cup kicks off — timing matters for prioritization below).

---

# PART A — AUDIT REPORT

## 1. Executive Summary

LiveGoal is technically excellent as an *application* (cache-first API, rate-limit-safe polling, resilient stale-on-failure, 50+ feature tests) but nearly invisible as a *website*. It is a pure client-rendered SPA: crawlers receive one static `<title>`, one static description, an inline loading animation, and an empty `<div id="app">` (`resources/views/app.blade.php:172`). There is no sitemap, no canonical, no Open Graph, no structured data, no per-page titles, and every URL — including garbage URLs — returns HTTP 200 (`routes/web.php:12`, proven by `tests/Feature/SpaShellTest.php`). The product domain (live scores + World Cup 2026) is one of the highest-search-volume verticals on the web, and the audit date is **the day before the World Cup starts** — every week of delay forfeits the single largest traffic event this product will ever see.

### Scores

| Dimension | Score | Rationale |
|---|---|---|
| **Overall** | **31/100** | Strong app foundation; almost no search surface |
| SEO (technical) | 18/100 | No SSR/meta/canonical/sitemap/schema; 200-for-everything fallback |
| Content | 25/100 | Rich live data, zero editorial/evergreen content, no crawlable HTML |
| Growth | 30/100 | Huge programmatic potential (matches × teams × competitions), none exploited |
| Performance | 62/100 | Excellent server caching; but 1100ms forced loader, Google Fonts, no HTTP cache headers |
| Conversion (retention) | 45/100 | Favorites + settings exist; no PWA install, notifications, or share loops |

### Top 20 Issues

| # | Issue | Where | Severity |
|---|---|---|---|
| 1 | No server-rendered content — crawlers/AI bots see an empty shell | `app.blade.php:172`, `routes/web.php:12` | Critical |
| 2 | One static `<title>` for every page on the site | `app.blade.php:7`; no `router.afterEach` in `resources/js/router/index.js` | Critical |
| 3 | One static meta description for every page | `app.blade.php:8` | Critical |
| 4 | No canonical tags anywhere — every query-string variant is a duplicate | `app.blade.php` head | Critical |
| 5 | No XML sitemap; `robots.txt` has no `Sitemap:` line | `public/robots.txt` | Critical |
| 6 | Every URL returns HTTP 200, including not-found pages — soft-404s + infinite crawl space | `Route::fallback` (`routes/web.php:12`), `tests/Feature/SpaShellTest.php:18-45` | Critical |
| 7 | No Open Graph / Twitter Card tags — shared links show no preview, killing the natural share loop of live scores | `app.blade.php` head | High |
| 8 | No structured data at all (no SportsEvent, BreadcrumbList, WebSite, Organization, FAQ) | entire `app/` and `resources/` | High |
| 9 | URLs are bare numeric IDs (`/match/524289`, `/team/57`) — no keywords, not memorable | `resources/js/router/index.js` routes | High |
| 10 | 1100ms minimum artificial boot-loader delay degrades LCP/INP on every visit | `resources/js/main.js:20` | High |
| 11 | Render-blocking Google Fonts stylesheet (3 families, 11 weights, third-party) | `app.blade.php:26-28` | Medium |
| 12 | No HTTP cache headers (`Cache-Control`/`ETag`) on `/api/*` — no CDN/browser caching despite app-layer TTLs | all `app/Http/Controllers/Api/*` | Medium |
| 13 | No `hreflang`/locale strategy and `lang` is dynamic but only `en` exists — fine today, blocks intl growth | `app.blade.php:2`, `config/app.php` | Low |
| 14 | `/scheduler/run` is a public GET route — should be robots-disallowed (token-guarded but crawlable noise) | `routes/web.php:10` | Low |
| 15 | No security headers (CSP/HSTS/X-Content-Type-Options) sent by app | `bootstrap/app.php` | Medium |
| 16 | No web app manifest / PWA — "live scores" is the canonical add-to-homescreen use case | `public/` | Medium |
| 17 | Crest/emblem images hotlinked from `crests.football-data.org` with no `width`/`height` attrs | `Crest.vue:42-43`, `CompetitionLogo.vue:42-43`, `LiveHub.vue:115` | Low |
| 18 | No analytics, no Search Console verification, no way to measure any of this | repo-wide (grep confirms zero analytics) | High |
| 19 | Zero editorial/evergreen content: no match previews, H2H, venue/stadium pages, FAQs, glossary | `resources/js/pages/` (data-only views) | High |
| 20 | Search page (`/search`) and parameterized states are client-only; no crawlable internal-link mesh between entities beyond JS | `resources/js/pages/Search.vue`, nav components | Medium |

### Top 20 Quick Wins (≤ half a day each)

1. Add `Sitemap:` line + `Disallow: /scheduler/` + `Disallow: /api/` to `public/robots.txt`.
2. Per-route `document.title` + meta description via `router.afterEach` (helps the 50%+ of Google renders that execute JS, and humans' tabs/bookmarks).
3. Dynamic titles on detail pages once data loads ("Arsenal vs Chelsea — Live Score | LiveGoal").
4. Server-side canonical tag in `app.blade.php` from `request()->path()` (strip all query strings).
5. Static Open Graph + Twitter Card defaults with a branded 1200×630 share image in `app.blade.php`.
6. `Organization` + `WebSite` JSON-LD hardcoded in `app.blade.php`.
7. Return real 404s: register known SPA path patterns explicitly and make `Route::fallback` return 404 status with the shell (Vue catch-all still renders NotFound).
8. Drop `MIN_DISPLAY_MS` 1100 → 0–300 in `resources/js/main.js:20`.
9. Self-host fonts (`@fontsource` packages) and trim to 2 families / ~5 weights.
10. Add `Cache-Control: public, max-age=30, stale-while-revalidate=60` (per-endpoint tuned) to API responses via middleware.
11. `<link rel="preconnect" href="https://crests.football-data.org">` in the blade head.
12. Add `width`/`height` attrs to `Crest.vue` / `CompetitionLogo.vue` imgs.
13. Noscript block with real links to competitions (crawlable nav even with JS off).
14. Web app manifest + theme-color → installable PWA shell.
15. Plug in privacy-friendly analytics (Plausible/Umami script tag) + Search Console DNS verification.
16. Add `descriptions`/headings text to hub pages (Competitions, Scorers, Matches) — 2-3 sentences of real indexable copy each.
17. `robots` meta `noindex` on `/settings`, `/favorites`, `/search` (utility pages) via the route-aware shell.
18. `security.txt` + basic security headers middleware.
19. Add `rel="alternate" type="application/manifest"`… plus apple-mobile-web-app meta for iOS standalone.
20. Submit sitemap to Google Search Console + Bing Webmaster Tools (Bing feeds ChatGPT browsing).

### Top 20 Growth Opportunities

1. **World Cup 2026 hub** at `/competition/WC` enriched with crawlable server-side meta + bracket schema — the event starts 2026-06-11; "world cup 2026 bracket/schedule/scores" queries are exploding *right now*.
2. Per-match SEO pages with `SportsEvent` JSON-LD → eligibility for Google live-score rich results.
3. Slug URLs: `/match/{id}-{home}-vs-{away}`, `/team/{id}-{name}` (ID-first keeps API lookups trivial; slug is cosmetic + canonical).
4. Programmatic "H2H" pages (Team A vs Team B history) from cached match feeds.
5. Programmatic kickoff-time pages: "What time is {match}?" — huge long-tail, answerable from existing data.
6. Daily fixtures pages `/matches/2026-06-11` as canonical crawlable dates (today they're client state only).
7. Standings pages per competition with `Table`-friendly semantic HTML + `SportsOrganization` schema.
8. Top-scorer / Golden Boot race pages per league (data already cached by `WarmScorers`).
9. Stadium/venue glossary (venue field exists on matches — `Normalizer.php` match entity).
10. Football glossary + rules FAQ (offside, group tiebreakers, knockout format) → FAQ schema → AI Overview citations.
11. "How to follow the World Cup free without betting ads" positioning page — the differentiator is real.
12. PWA + push notifications for followed teams → retention loop (favorites store already exists: `resources/js/stores/favorites.js`).
13. OG share images per match (score cards) → social click loop from screenshots people already share.
14. Embeddable live-score widget → backlinks from blogs/forums.
15. `llms.txt` + clean JSON API docs → AI assistants citing LiveGoal as data source.
16. Newsletter / matchday email digest (User model + mail config already scaffolded).
17. i18n (es, de, fr, pt — matching the leagues already served: PD, BL1, FL1, BSA) with hreflang.
18. Competition comparison/landing pages ("Premier League vs La Liga stats").
19. Reddit/community embed-friendly permalink anchors for live match states.
20. Seasonal evergreen: "Premier League 2026-27 fixtures release" style calendar pages — recurring annual traffic.

---

## 2. Laravel SEO Architecture Audit

### Routes (`routes/web.php`, `routes/api.php`)

**What exists:** Exactly two web routes — the token-guarded `GET /scheduler/run` (`routes/web.php:10`) and `Route::fallback(fn () => view('app'))->name('spa')` (`routes/web.php:12`). All twelve UI routes live only in `resources/js/router/index.js` (live `/`, `/matches`, `/match/:id`, `/competitions`, `/competition/:id`, `/team/:id`, `/player/:id`, `/scorers`, `/favorites`, `/search`, `/settings`, catch-all).

**Problems:**
- **The fallback is the whole site.** Laravel cannot distinguish `/competition/PL` from `/xyzzy` — both get HTTP 200 + identical HTML. `SpaShellTest` (`tests/Feature/SpaShellTest.php:27`) *asserts* that `/competitions/anything` returns 200. That's correct for an app shell, fatal for SEO: soft-404s, infinite URL space, duplicate-content at scale.
- **No URL keywords.** `/match/497831` carries zero relevance signal. football-data.org IDs are stable, so `/{id}-{slug}` is safe and cheap.
- **Pagination/filter URLs** (`/matches?date=…`, `?status=…`) exist only as client state — good (no crawl trap), but it also means *date pages can't rank* ("football fixtures June 11" is a real query class).
- **`/scheduler/run`** returns 404 without a token (good, `SchedulerController.php:21` uses `hash_equals`), but should still be `Disallow`ed in robots.txt to avoid crawl noise and log spam.

**Fixes:** enumerate the real SPA paths server-side (a single route group pointing at a `SeoShellController`), keep `Route::fallback` only for true 404s (status 404, shell still rendered so the Vue NotFound page shows).

### Middleware (`bootstrap/app.php`)

- Only `AddLinkHeadersForPreloadedAssets` is appended (`bootstrap/app.php:18`) — good for asset preload.
- Trailing-slash redirect exists at the Apache layer (`public/.htaccess`), **not** in the app — Nginx deployments (per `docs/DEPLOY.md`) won't get it. Add canonical-host + trailing-slash handling app-side or document it as a server requirement.
- No www/non-www canonicalization, no security headers, no compression (server-delegated — must be verified in deployment).
- API exceptions render JSON (`bootstrap/app.php:22-24`) — good.

### Controllers (`app/Http/Controllers/Api/*`)

- Clean, cache-first, validated query params (e.g. `CompetitionController.php:44-50`), consistent `{data, meta}` envelope with `stale`/`cached`/`lastUpdated` (`Api/Controller.php`) — excellent app architecture.
- **Zero SEO involvement:** no controller generates meta, canonical, schema, or OG data, because no controller renders HTML at all. The SEO shell controller (Part B, Task 2) closes this gap by *reusing these exact cached reads*.
- **No HTTP caching:** responses carry no `Cache-Control`/`ETag` despite the server knowing precise TTLs (`config/football.php` `ttl` map). A CDN in front of `/api` currently caches nothing.

---

## 3. Meta SEO Audit (`resources/views/app.blade.php`)

| Element | Status | Detail |
|---|---|---|
| `<title>` | ⚠️ Static | One title site-wide (`app.blade.php:7`) |
| Meta description | ⚠️ Static | One description site-wide (`:8`) |
| Robots directives | ❌ None | No `meta robots`; utility pages (settings/search/favorites) indexable |
| Canonical | ❌ Missing | Query-string variants & host variants all duplicate |
| hreflang | ➖ N/A today | Single locale; `lang` attr correctly dynamic (`:2`) |
| Open Graph | ❌ Missing | Links shared to WhatsApp/X/Discord (core football behavior) render bare |
| Twitter cards | ❌ Missing | — |
| Favicons | ✅ Good | SVG + PNG + ICO + apple-touch (`:21-24`) |
| Theme/viewport | ✅ Good | `viewport-fit=cover`, pre-paint theme script (`:11-19`) |

**Scalability issue:** there is no mechanism for per-page meta at all — neither server-side (single blade) nor client-side (no `router.afterEach`, no `useHead`). Both layers are needed: server-side for crawlers/social bots (most don't execute JS), client-side for tab titles and JS-rendering crawlers.

---

## 4. Structured Data Audit

**What exists:** Nothing. No JSON-LD, no microdata, anywhere (verified across `app/` and `resources/`).

**What should exist (all generatable from already-cached data):**

| Schema | Source data | Pages |
|---|---|---|
| `Organization` + `WebSite` (with `SearchAction`) | static | shell (all pages) |
| `SportsEvent` (with `homeTeam`, `awayTeam`, `startDate`, `eventStatus`, `location` from venue) | `fd:match:{id}` cache, `Normalizer::match()` | `/match/{id}` |
| `SportsTeam` | `fd:team:{id}` | `/team/{id}` |
| `Person` (athlete) | `fd:person:{id}` | `/player/{id}` |
| `SportsOrganization` / `EventSeries` | `fd:competition:{id}` | `/competition/{id}` |
| `BreadcrumbList` | route hierarchy | all detail pages |
| `ItemList` of `SportsEvent` | `fd:matches` day feed | `/`, `/matches` |
| `FAQPage` | new content (Part B, Phase 3) | FAQ/glossary pages |

`SportsEvent` is the highest-value item: Google's live-scores rich results and AI Overviews for "X vs Y score" pull from it.

---

## 5. Content System Audit

**Database reality:** there are no content tables. Migrations are only `users`, `cache`, `jobs` (`database/migrations/`). All football entities are normalized JSON from football-data.org living in the `cache` table (`app/Services/Football/Normalizer.php`). There is no CMS, no posts/pages/articles model.

**Content types (virtual, from cache):** competitions (13 free-tier codes, `config/football.php:22`), teams + squads, matches, standings, scorers, persons.

**SEO potential vs risk:**
- **Potential:** ~13 competitions × ~20 teams × ~380 matches/season + players = tens of thousands of legitimate, query-matching pages — *if* they render crawlable HTML with unique meta.
- **Thin-content risk:** a bare data page ("Team 57, founded 1886") is thin. Mitigate by composing: team page = profile + next/recent matches + position in table + top scorer (all cached already) — that's a substantive page.
- **Duplicate risk:** same match reachable via `/match/{id}` with/without slug, with query params, via competition pages → solved by canonical (Part B, Task 2) and slug-redirect (Task 8).
- **Volatility:** free-tier data only persists 24h (last-known-good). Long-term, persisting normalized entities into real tables (sqlite is fine) would let finished seasons remain crawlable forever — flagged as Phase 4, not required for launch.

---

## 6. Internal Linking Audit

**What exists (client-side only):**
- `TopNav.vue:15-36` — 5 primary destinations; `TabBar.vue` mobile mirror.
- Dense entity cross-links: match cards → match, standings rows → team, squad → player, scorer rows → player/team, competition tiles → competition. As a *user* graph it's good.

**Problems:**
- **None of it exists in initial HTML.** A non-JS crawler sees zero `<a href>` links — the entire site is one orphan page from a link-graph perspective.
- Many links are `router.push()` on click handlers (e.g. `MatchCard.vue` emits `open`), not real `<router-link>` anchors — even JS-rendering crawlers can't follow them, and users can't middle-click/copy-link.
- No breadcrumbs anywhere (no component exists).

**Recommendations:** convert click-handler navigation to `<router-link>`/`<a>` (Task 10); add breadcrumb component + `BreadcrumbList` schema; add a crawlable footer link block (competitions list) into the blade shell; structure hubs as Competition → (standings | fixtures | scorers | teams) → team → players topic silos.

---

## 7. Programmatic SEO Opportunities

All driven from caches that already exist (no new upstream quota):

| Page type | URL pattern | Query class | Est. potential |
|---|---|---|---|
| Match pages | `/match/{id}-{home}-vs-{away}` | "arsenal vs chelsea score/lineups/time" | Very high (head + long tail, ~380/season/league) |
| Daily fixtures | `/matches/{yyyy-mm-dd}` | "football matches today/june 11" | High, evergreen-recurring |
| Kickoff time | section on match page + FAQ schema | "what time is france vs brazil" | High during WC |
| Standings | `/competition/{code}/standings` (server-known) | "premier league table" | Very high head terms |
| Top scorers | `/competition/{code}/scorers` | "golden boot 2026", "PL top scorers" | High |
| H2H | `/h2h/{teamA}-vs-{teamB}` | "real madrid vs barcelona head to head" | Medium-high |
| Team hubs | `/team/{id}-{slug}` | "{team} fixtures/results/squad" | Very high |
| Glossary/FAQ | `/guide/{slug}` | "what is offside", "world cup tiebreakers" | Medium, AI-citation-friendly |

Realistic ceiling with WC 2026 timing: live-score head terms are dominated by Google's own widget + giants (Flashscore, Sofascore, BBC), but the long tail (kickoff-time, H2H, group-permutation, "without betting" qualifiers) is winnable, and AI engines reward clean structured data over domain authority more than classic search does.

---

## 8. Performance Audit

### Frontend
- ✅ Route-level code splitting (all pages lazy `() => import()` in `router/index.js`).
- ✅ Images lazy-loaded with alt text (`Crest.vue:42-43`).
- ❌ **`MIN_DISPLAY_MS = 1100`** (`main.js:20`): every visit waits ≥1.1s behind a full-screen overlay. Real LCP becomes ~1.1s + hydrate + first API call. This is the single biggest perceived-perf cost and it's self-inflicted.
- ❌ Google Fonts: 3 families / 11 weights, render-blocking third-party CSS (`app.blade.php:28`). Self-host + subset → ~200-400ms FCP gain on cold loads.
- ❌ No `preconnect` to `crests.football-data.org` though every page loads crests from it.
- ⚠️ No `width`/`height` on crest `<img>`s (CSS-sized, so CLS is mostly contained, but attrs are free insurance).

### Laravel
- ✅ Outstanding cache architecture: TTL-tuned `Cache::remember` per entity (`config/football.php` ttl map), warmers (`WarmMatches` every minute, `WarmScorers` every 10m), single live poller, last-known-good stale fallback (`FootballData.php:89-107`). No N+1 risk (no Eloquent in the hot path at all).
- ❌ No HTTP `Cache-Control`/`ETag` on API responses — browsers re-fetch fully every poll; a CDN can't help.
- ⚠️ Cache store is `database` (SQLite). Fine at current scale; Redis/file is a later optimization, not a blocker.
- ⚠️ `php artisan route:cache`/`config:cache` should be in the deploy script (verify `docs/DEPLOY.md`).

---

## 9. Content Growth Audit — 50+ content ideas

**Pillar 1: World Cup 2026 (urgent — tournament starts 2026-06-11)**
1. WC 2026 hub (schedule, bracket, groups) 2. Group-by-group guides (A–L) 3. Knockout bracket explained + tiebreaker rules 4. Host cities & stadiums guide 5. "What time are today's WC matches" daily page 6. Golden Boot race tracker 7. How the 48-team format works 8. Third-place qualification permutations explained 9. WC glossary (VAR, added time, squad rules) 10. "Follow the WC free without betting ads" landing page 11. Each round preview hub (R32, R16, QF, SF, F) 12. Penalty shootout rules FAQ.

**Pillar 2: League hubs (PL, PD, SA, BL1, FL1, DED, PPL, ELC, BSA)**
13. {League} live scores & table hub 14. {League} fixtures by matchday 15. {League} top scorers 16. {League} relegation/qualification zones explained 17. {League} season calendar ("when does the 2026-27 season start") 18. Promotion/relegation rules per league 19. European qualification spots explained 20. {League} form guide (last-5 table — data exists in `Normalizer` standings `form`).

**Pillar 3: Match & team long-tail (programmatic)**
21. Match preview pages (kickoff, venue, referee — all in match entity) 22. H2H pages for rivalry pairs 23. Team fixture pages "{team} next match" 24. Team results pages 25. Squad pages by position 26. Player profile pages (age, nationality, position from `fd:person`) 27. "Where is {match} played" venue answers 28. Derby/rivalry hub pages 29. Matchday live-blogs (auto-built from goal events the poller already detects, `PollLiveScores.php:47-60`) 30. Daily "all finished scores" recap pages.

**Pillar 4: Football knowledge (FAQ/glossary — AI-search bait)**
31. What is offside (2026 rules) 32. How does goal difference work 33. What do W/D/L and GD/Pts mean (the app shows these columns — `StandingsTable.vue`) 34. How extra time & penalties work 35. What is a group-stage tiebreaker 36. How do Champions League qualifications work 37. Football positions explained (squad data groups by position) 38. What does "aggregate" mean 39. How injury/added time is decided 40. What is xG (and why LiveGoal shows real scores instead).

**Pillar 5: Product/utility content**
41. How to follow your team (favorites feature tour) 42. Timezone guide — "kickoff times in your timezone" (Settings has timezone support) 43. LiveGoal vs betting-ad score apps comparison 44. How LiveGoal data works / data sources & update frequency (transparency page — also AI-citation friendly) 45. API/embed widget docs 46. Accessibility statement 47. "Best free live-score sites 2026" honest roundup 48. Push notification setup guide 49. PWA install guide (iOS/Android) 50. About/contact/editorial policy (E-E-A-T trust pages).

**Clusters:** each league hub (pillar 2) is a cluster head; match/team pages (pillar 3) interlink up to it; knowledge pages (pillar 4) interlink contextually from UI surfaces (e.g., a "?" next to the GD column → goal-difference guide).

---

## 10. Conversion Optimization Audit

LiveGoal is free/non-betting, so "conversion" = retention + habit + distribution:

- ✅ Favorites system exists (`stores/favorites.js`, `FavoriteStar.vue`, Favorites page) — the core retention primitive is built.
- ❌ No onboarding nudge to follow a team (first-visit empty Favorites is a dead end; `Favorites.vue:88-105` empty state has art but no "pick your team" flow).
- ❌ No PWA install prompt or manifest — for a daily-habit product this is the #1 retention lever.
- ❌ No notifications (goal alerts for followed teams) — the poller already detects goal changes (`PollLiveScores.php:47-60`) and `GoalToast.vue` exists in-session; push is the natural extension.
- ❌ No share affordances on match pages (and no OG tags, so shares look broken anyway).
- ❌ No trust/about pages (who runs this? data source? update cadence?) — matters for users *and* E-E-A-T.
- ❌ No analytics — can't measure any funnel.

---

## 11. AI Search Optimization Audit

AI engines (ChatGPT browsing, Perplexity, Gemini, AI Overviews) need: fetchable HTML (most do **not** execute JS), entity-dense structured data, and quotable factual statements.

- ❌ Today, an AI crawler fetching any LiveGoal URL gets the boot-loader HTML and nothing else — LiveGoal cannot be cited.
- ❌ No FAQ/definitional content to be quoted.
- ❌ No `llms.txt`, no API docs page that AI tools could use to recommend LiveGoal as a data source.
- ✅ The JSON API itself is clean and well-shaped — exposing a documented, linkable subset is a differentiator.

**Fixes:** the server-side SEO shell (crawlable HTML + JSON-LD) is 80% of AI readiness; FAQ/glossary pages with `FAQPage` schema are the quotable layer; `llms.txt` + "how our data works" page completes it. Bing Webmaster submission matters specifically because ChatGPT browsing uses Bing's index.

## 12. Competitor & Market Position

Inferred competitors: Flashscore, Sofascore, FotMob, LiveScore, AiScore, BBC Sport, Google's own score widget. All are betting-ad-funded except BBC/Google. **The non-betting, no-account, fast-and-clean position is real and underserved** — several markets (UK esp.) have growing bettingads fatigue. Missing vs competitors: lineups/stats depth (paid-tier data — out of scope), news (out of scope by spec), notifications, widget/embeds, apps. Strategy: don't fight head terms; win (a) WC 2026 long-tail *now*, (b) "without betting" qualifiers, (c) AI-engine citations via superior structured data, (d) PWA habit loop.

## 13. Security & SEO-Impact Audit

- Every URL 200s → soft-404 index bloat (covered above, top issue #6).
- Unbounded query strings on the fallback (`/match/1?utm=…&x=y`) all 200 → canonical fixes.
- `/scheduler/run` rate-limited (`throttle:20,1`) + timing-safe token (`SchedulerController.php:21`) — solid; just disallow in robots.
- `/up` health endpoint exposed (`SpaShellTest.php:54`) — harmless, add robots disallow.
- API is open/unauthenticated by design (read-only, cached) — fine; add `Cache-Control` + existing throttle is recommended (`/api` currently has **no throttle** — worth adding `throttle:60,1`).
- No `X-Content-Type-Options`/`X-Frame-Options`/CSP headers app-side.
- Client search is local-only (no indexable search-result URLs) — no search-page spam risk. Keep `/search` noindex anyway.

## 14. 90-Day Roadmap (summary — details in Part B)

| Phase | When | Theme | Items |
|---|---|---|---|
| **P0 — this week** (WC starts tomorrow) | Days 1–5 | Crawlable shell + WC hub | Tasks 1–7: robots+sitemap, SEO shell controller (meta/canonical/OG/JSON-LD), real 404s, client titles, fonts+loader perf, analytics |
| **P1** | Weeks 2–4 | Index depth | Tasks 8–11: slugs, crawlable links/breadcrumbs, date pages, HTTP caching, OG share images |
| **P2** | Weeks 5–8 | Content layer | FAQ/glossary system, league hub copy, trust pages, llms.txt, PWA manifest + install |
| **P3** | Weeks 9–12 | Retention + reach | Push notifications, embeds/widget, i18n groundwork, persist entities to DB for permanent archives |

Impact/effort per item is listed with each task below.

---

# PART B — CHANGES PLAN (prioritized implementation tasks)

## GEO/AEO research findings — 2026-06-10 (validated against current primary sources)

Researched and grilled before building the event-ranking layer. Key evidence:

- **AI answer crawlers do NOT execute JavaScript** — GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, PerplexityBot all read raw HTML only (Vercel 569M-request analysis; searchVIU Nov 2025). Only Googlebot, Applebot, and (partially) Bingbot render JS. → **Server-rendering the facts is mandatory for AI/GEO visibility**, not optional.
- **Pre-rendering facts into `#app` (Vue replaces on mount) is compliant** — same HTML to all clients = content parity = SSR-style, explicitly NOT cloaking and NOT the deprecated "dynamic rendering" (Google spam-policies 2026-05-15; dynamic-rendering doc 2025-12-10). Caveat honoured: the prerender shows the same data the SPA renders.
- **Validated GEO levers** (KDD 2024 causal study): quotations +41%, statistics/numbers +34%, citing sources +28%, fluency +30%; **keyword stuffing −8% (hurts)**. → Prerendered copy is factual + number-dense (e.g. "X lead the league on N points after M matches").
- **Freshness (QDF)** genuinely helps for live sports, but **only if honest** — Google's Dec 2025 core update demoted fake date-bumping. → "Last updated" reflects the real cache fetch time; not written to sitemap `lastmod`.
- **Dropped / de-scoped on evidence:** `llms.txt` (every major engine ignores it; Google warns against it); relying on schema for the score/live state (schema.org `SportsEvent` has **no score field, no live/finished `eventStatus`**, and there is **no sports rich result**); FAQ rich-result markup (**FAQ rich results fully removed 2026-05-07** — FAQ *content* still helps AEO, the markup buys nothing in Google).
- **Can't win** Google's live-score SERP card / AI Mode scores (licensed data, not crawled). → Win the long tail + crawlable readable pages instead.
- **Structural edge:** Flashscore & Sofascore are client-rendered SPAs shipping empty HTML with **no structured data** — server-rendered facts + schema is something the category leaders don't do.
- **Positioning:** "free, no betting, no ads, clean" has a real tailwind (PL front-of-shirt gambling-sponsorship ban from 2026/27; FotMob praised for being ad-light).

Full research briefs (with source URLs + dates) are in the conversation that produced this update.

## Implementation status — updated 2026-06-10

**P0 + GEO/AEO prerender shipped (Tasks 1–5 + Task 6 partial + server-rendered facts).** Gate green: 99/99 PHPUnit tests (25 SEO tests), PHPStan level max 0 errors, Pint/ESLint/Prettier clean, build OK.

- ✅ **Server-rendered facts (the GEO/AEO unlock)** — match/competition/team/player pages now prerender the actual facts into `#app` (Vue replaces on mount): factual number-dense summary, scores, standings tables, top scorers, fixtures, and an honest "last updated". Built from cache only (`FootballData::peekEntry`, never hits upstream). Files: `app/Seo/SeoMetaResolver.php` (`matchBody`/`competitionBody`/`teamBody`/`playerBody`), `resources/views/seo/{match,competition,team,player}.blade.php`, `SeoShellController` + `app.blade.php`. Tests: `tests/Feature/Seo/SeoPrerenderTest.php`.

**P0 shipped (Tasks 1–5 + Task 6 partial).** All on branch-pending (not yet committed). Gate green: 93/93 PHPUnit tests (19 new SEO tests), PHPStan level max 0 errors, Pint/ESLint/Prettier clean, production build OK.

- ✅ **Task 1** — Dynamic `robots.txt` + `sitemap.xml` (`app/Http/Controllers/SitemapController.php`, routes, static `public/robots.txt` deleted). Tests: `tests/Feature/Seo/SitemapTest.php`.
- ✅ **Task 2** — Server-side SEO shell: per-URL title/description/canonical/robots/OG/Twitter/JSON-LD (`app/Seo/SeoMeta.php`, `app/Seo/SeoMetaResolver.php`, `app/Http/Controllers/SeoShellController.php`, `config/seo.php`, `FootballData::peek()` cache-only accessor, rewritten `routes/web.php` + `resources/views/app.blade.php`). Tests: `tests/Feature/Seo/SeoShellTest.php`. Competition pages resolve their name from the warmed `fd:competitions` list even when the per-competition detail is cold.
- ✅ **Task 3** — Real 404 status for unknown paths (`Route::fallback` → `SeoShellController::notFound`, 404 + shell). `tests/Feature/SpaShellTest.php` updated.
- ✅ **Task 4** — Crawlable `<noscript>` nav in the shell (links to hubs + every featured competition).
- ✅ **Task 5** — Client-side dynamic titles: `resources/js/composables/usePageMeta.js`, per-route `meta.title` + guarded `router.afterEach` (skips first nav so the server title survives), wired into Match/Team/Competition/Player detail pages.
- ✅ **Task 6 (partial)** — Boot-loader floor 1100ms → 250ms (`resources/js/main.js`); crest `preconnect` in shell; `width`/`height` on `Crest.vue` + `CompetitionLogo.vue` images.

**Pending decisions — RESOLVED 2026-06-10 (domain `livegoal.win`):**
- ✅ **Production domain** — `APP_URL=https://livegoal.win` set in `.env` + `.env.example` (and the stale `APP_NAME=Laravel`/`socplay.test` in `.env` fixed). Canonical/OG/sitemap now resolve to livegoal.win.
- ✅ **Task 6 (fonts)** — self-hosted via `@fontsource/{saira-condensed,hanken-grotesk,geist-mono}` imported in `resources/css/app.css`; Google Fonts `<link>`s removed from the shell (no render-blocking third-party request). Build bundles woff2 per unicode-range.
- ✅ **Task 7 (analytics)** — Plausible (privacy-friendly, cookieless) wired in `config/services.php` + shell, gated on `PLAUSIBLE_DOMAIN` (off until set). **One manual step left:** add livegoal.win in a Plausible account, then set `PLAUSIBLE_DOMAIN=livegoal.win`. Search Console/Bing submission still manual.
- ✅ **OG share image** — `php artisan og:generate` (new `App\Console\Commands\GenerateOgImage`, GD-based) produces the bundled 1200×630 `public/og-image.png`; `config/seo.php` defaults `og_image=/og-image.png` + `og_image_wide=true` (`summary_large_image`). Test: `tests/Feature/GenerateOgImageTest.php`.

Gate after these: 100/100 PHPUnit, PHPStan max 0 errors, Pint/ESLint/Prettier clean, build OK.

**Not started (P1+):** Tasks 8–14 (slugs, HTTP cache headers + API throttle, real anchor links/breadcrumbs, date pages, content layer, PWA manifest, OG image generation).

---

Conventions for all tasks: PHPUnit (not Pest), `php artisan test --compact --filter=…` after each test, `vendor/bin/pint --dirty --format agent` before each commit, PHPStan must stay green (`composer analyse`). Branch per repo convention: `phase/seo-<n>-<slug>` off `dev`.

> Each task lists **Fixes issues:** referencing the Top-20 issue numbers from Part A §1 for traceability.

---

### Task 1: robots.txt + sitemap.xml
**Fixes issues:** #5, #14. **Impact:** High · **Effort:** S

**Files:**
- Modify: `public/robots.txt`
- Create: `app/Http/Controllers/SitemapController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SitemapTest.php`

- [ ] **Step 1: Write failing test** — `php artisan make:test --phpunit SitemapTest`

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_sitemap_lists_static_and_competition_urls(): void
    {
        Cache::put('fd:competitions', [
            'fetched_at' => now()->toIso8601String(),
            'payload' => ['competitions' => [['id' => 2021, 'code' => 'PL', 'name' => 'Premier League']]],
        ], 600);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml');
        $this->assertStringContainsString('<loc>' . url('/competitions') . '</loc>', $response->getContent());
        $this->assertStringContainsString('<loc>' . url('/competition/PL') . '</loc>', $response->getContent());
    }
}
```

(Adjust the `Cache::put` payload shape to match what `FootballData::cached()` stores — check `app/Services/Football/FootballData.php` fresh-key write and reuse the same shape the existing Api tests use, e.g. `tests/Feature/Api/CompetitionsTest.php` fixtures.)

- [ ] **Step 2: Run, verify fails** — `php artisan test --compact --filter=SitemapTest` → FAIL (404).
- [ ] **Step 3: Implement controller** — reads cached competitions (same read path as `CompetitionController::index`, refresh=false), emits `<urlset>` with: `/`, `/matches`, `/competitions`, `/scorers`, each `/competition/{code}`, plus per-competition subpaths. Wrap in `Cache::remember('sitemap', 3600, …)`. Route: `Route::get('sitemap.xml', SitemapController::class)` **above** the fallback.
- [ ] **Step 4: Update robots.txt:**

```
User-agent: *
Disallow: /scheduler/
Disallow: /api/
Disallow: /settings
Disallow: /favorites
Disallow: /search
Disallow: /up

Sitemap: https://<production-domain>/sitemap.xml
```

(Use a relative `Sitemap:` is invalid — generate robots.txt dynamically via a route OR document the domain at deploy. Prefer dynamic: `Route::get('robots.txt', …)` using `url()`, and delete the static file.)

- [ ] **Step 5: Run tests, Pint, commit** — `feat(seo): add sitemap.xml and tighten robots.txt`

---

### Task 2: Server-side SEO shell (per-URL title/description/canonical/OG/JSON-LD)
**Fixes issues:** #1 (partially), #2, #3, #4, #7, #8. **Impact:** Critical — this is the core change. **Effort:** L

**Architecture decision:** No SSR framework. The blade shell becomes parameterized: a `SeoShellController` resolves the requested path against known SPA patterns, pulls the matching entity from the **existing cache (refresh=false — never burns upstream quota on crawler traffic)**, and passes a `$seo` value-object to `app.blade.php` (title, description, canonical, og array, jsonLd array, robots). Unknown entities or cold cache → sensible per-route-type defaults. Crawlers get real meta + JSON-LD + a small noscript content block; users get the identical SPA.

**Files:**
- Create: `app/Seo/SeoMeta.php` (readonly value object: title, description, canonical, robots, og[], jsonLd[])
- Create: `app/Seo/SeoMetaResolver.php` (path → SeoMeta; injects `FootballData`)
- Create: `app/Http/Controllers/SeoShellController.php`
- Modify: `routes/web.php` — explicit GET routes for `/`, `/matches`, `/match/{id}`, `/competitions`, `/competition/{id}`, `/team/{id}`, `/player/{id}`, `/scorers`, `/favorites`, `/search`, `/settings` → `SeoShellController`; fallback stays for 404s (Task 3)
- Modify: `resources/views/app.blade.php` — head consumes `$seo` with current values as defaults
- Test: `tests/Feature/SeoShellTest.php`

- [ ] **Step 1: Failing tests** (representative; one per route family):

```php
public function test_match_page_has_event_meta_and_jsonld(): void
{
    // seed fd:match:1 cache with normalized fixture (reuse Api/MatchesTest fixture helper)
    $this->get('/match/1')
        ->assertOk()
        ->assertSee('<title>Arsenal vs Chelsea', false)
        ->assertSee('<link rel="canonical" href="' . url('/match/1') . '">', false)
        ->assertSee('"@type":"SportsEvent"', false)
        ->assertSee('property="og:title"', false);
}

public function test_cold_cache_falls_back_to_generic_meta(): void
{
    $this->get('/match/999')->assertOk()->assertSee('<title>Match Centre', false);
}

public function test_settings_is_noindex(): void
{
    $this->get('/settings')->assertOk()->assertSee('noindex', false);
}

public function test_query_strings_do_not_change_canonical(): void
{
    $this->get('/match/1?utm_source=x')
        ->assertSee('<link rel="canonical" href="' . url('/match/1') . '">', false);
}
```

- [ ] **Step 2: Run, verify fail.**
- [ ] **Step 3: Implement `SeoMeta` + `SeoMetaResolver`.** Title patterns:
  - `/` → `Live Football Scores Today — World Cup 2026, Premier League & More | LiveGoal`
  - `/match/{id}` → `{Home} vs {Away} — Live Score & Result ({Competition}) | LiveGoal`; description includes kickoff UTC, venue, stage; JSON-LD `SportsEvent` with `eventStatus` mapped from normalized status (`SCHEDULED→EventScheduled`, `LIVE/HT→…`, `FT→…`), `BreadcrumbList`.
  - `/competition/{id}` → `{Name} {SeasonYears} — Table, Fixtures & Results | LiveGoal`, JSON-LD `SportsOrganization` + breadcrumbs.
  - `/team/{id}` → `{Team} — Fixtures, Results & Squad | LiveGoal`, `SportsTeam`.
  - `/player/{id}` → `{Name} — {Position}, {Team} | LiveGoal`, `Person`.
  - `/scorers`, `/matches`, `/competitions` → static rich titles/descriptions.
  - `/settings`, `/favorites`, `/search` → defaults + `robots: noindex,follow`.
  - Site-wide JSON-LD: `Organization` + `WebSite` (with `potentialAction` SearchAction → `/search?q={query}` even though search is client-side).
  All entity reads MUST use refresh=false cache reads (add/reuse a read-only accessor on `FootballData`) so crawler floods can't trigger upstream calls.
- [ ] **Step 4: Wire blade:** escape everything (`{{ }}`/`@json`), keep existing loader/theme markup untouched; add a `<noscript>` block (Task 4 expands it).
- [ ] **Step 5: Tests green → PHPStan → Pint → commit** — `feat(seo): route-aware meta, canonical, Open Graph and JSON-LD in SPA shell`. Suggest splitting commits: VO+resolver / routes+controller / blade.

---

### Task 3: Real 404 status for unknown URLs
**Fixes issues:** #6. **Impact:** High · **Effort:** S. **Depends on Task 2** (explicit routes must exist first).

**Files:** Modify `routes/web.php` fallback; Modify `tests/Feature/SpaShellTest.php`; Test additions in `SeoShellTest.php`.

- [ ] **Step 1: Update tests** — `SpaShellTest::test deep links` keeps asserting 200 for *known* patterns; **change** the `'/competitions/anything'` expectation: that path isn't a real route shape (`/competitions/{x}` ≠ `/competition/{id}`) so it must now assert 404. Add: `$this->get('/totally/unknown')->assertNotFound();` and assert body still contains `id="app"` (shell renders so Vue shows NotFound page).
- [ ] **Step 2: Implement** — `Route::fallback(fn () => response()->view('app', ['seo' => SeoMeta::notFound()], 404))->name('spa');`
- [ ] **Step 3: Also 404 unknown entities?** No — keep 200+generic meta when cache is cold (entity may be real but uncached); only pattern-level misses 404. Add `noindex` to cold-cache entity pages instead (one-line in resolver) so junk IDs never index.
- [ ] **Step 4: Tests, Pint, commit** — `fix(seo): return 404 status from SPA fallback for unknown paths`

---

### Task 4: Crawlable noscript nav + footer links
**Fixes issues:** #20, §6 orphan risk. **Impact:** Medium · **Effort:** S

**Files:** Modify `resources/views/app.blade.php`; Modify `tests/Feature/SeoShellTest.php`.

- [ ] Add inside `<noscript>` (and as a plain hidden-from-app footer is unnecessary — noscript suffices for non-JS crawlers): a `<nav>` with real `<a href>` links to `/`, `/matches`, `/competitions`, `/scorers` and each featured competition (`config/football.php` featured list, rendered server-side), plus a one-paragraph site description. Test asserts the links exist in the raw HTML.
- [ ] Commit — `feat(seo): crawlable noscript navigation in shell`

---

### Task 5: Client-side dynamic titles & meta sync
**Fixes issues:** #2 (user-facing half), #18 partially. **Impact:** Medium · **Effort:** S

**Files:** Modify `resources/js/router/index.js` (route `meta.title` + `router.afterEach`); Create `resources/js/composables/usePageMeta.js`; Modify detail pages (`MatchDetail.vue`, `TeamDetail.vue`, `CompetitionDetail.vue`, `PlayerDetail.vue`) to call `usePageMeta(title)` when data resolves (e.g. `Arsenal 2–1 Chelsea` live-updating in the tab).

- [ ] Implement `usePageMeta(titleRef)` → watches and sets `document.title` and the `meta[name=description]` element; `router.afterEach` sets static fallbacks from `to.meta.title`.
- [ ] No PHP tests; verify with `npm run lint` + manual check via Herd URL (`get-absolute-url`).
- [ ] Commit — `feat(seo): dynamic document titles per route and live match titles`

---

### Task 6: Performance — loader delay, fonts, preconnect, img dims
**Fixes issues:** #10, #11, #17. **Impact:** High (Core Web Vitals are a ranking input) · **Effort:** M

- [ ] **Loader:** `resources/js/main.js:20` — `MIN_DISPLAY_MS` 1100 → 250. (Keep the loader for slow loads; stop taxing fast ones.)
- [ ] **Fonts:** replace Google Fonts `<link>`s in `app.blade.php:26-28` with self-hosted `@fontsource-variable`/`@fontsource` imports in `resources/css/app.css`; trim weights: Saira Condensed 600/700/800, Hanken Grotesk 400/500/700, Geist Mono 400/500. Requires approval to add npm deps (`@fontsource/*`) per CLAUDE.md dependency rule — **flagging for your OK**.
- [ ] **Preconnect:** add `<link rel="preconnect" href="https://crests.football-data.org" crossorigin>`.
- [ ] **Img dims:** add `width`/`height` attrs matching CSS sizes in `Crest.vue`, `CompetitionLogo.vue`, `LiveHub.vue` WC emblem.
- [ ] Verify: `npm run build` clean, Lighthouse before/after via Herd URL.
- [ ] Commit — `perf: trim boot delay, self-host fonts, preconnect crests, image dimensions`

---

### Task 7: Analytics + Search Console
**Fixes issues:** #18. **Impact:** High (measurement precondition) · **Effort:** S

- [ ] Add Plausible (or Umami) script tag to `app.blade.php` behind `config('services.plausible.domain')` env so dev stays clean; track SPA route changes via `router.afterEach`. **Needs your choice of provider + account — flagging.**
- [ ] Verify domain in Google Search Console + Bing Webmaster (DNS), submit sitemap. (Manual, listed for completeness.)
- [ ] Commit — `feat: privacy-friendly analytics with SPA pageview tracking`

---

### Task 8: Slug URLs (`/match/{id}-{slug}`, `/team/{id}-{slug}`, `/player/{id}-{slug}`)
**Fixes issues:** #9. **Impact:** Medium-high · **Effort:** M. **Phase 1.**

- [ ] Frontend: route patterns become `/match/:id(\\d+)(-.*)?`-style (Vue Router keeps `props: id` numeric-parsed); all `router.push`/`router-link` call a new `lib/slugs.js` helper `matchPath(m)` → `/match/${m.id}-${kebab(home.short)}-vs-${kebab(away.short)}`.
- [ ] Backend: `SeoMetaResolver` parses leading numeric id; canonical always emits the *sluggged* URL; when request path ≠ canonical slug path → controller returns 301 redirect (server-side) for crawler-grade canonicalization.
- [ ] Tests: `SeoShellTest` — `/match/1` 301→`/match/1-arsenal-vs-chelsea` when cached; cold cache → 200 no redirect.
- [ ] Commit per sub-step.

---

### Task 9: HTTP cache headers + API throttle
**Fixes issues:** #12, §13 throttle gap. **Impact:** Medium · **Effort:** S-M

- [ ] Create `app/Http/Middleware/SetApiCacheHeaders.php`: map route → `Cache-Control` from `config/football.php` ttl values (e.g. live: `public, max-age=15, stale-while-revalidate=30`; competitions: `public, max-age=3600`); add `ETag` (md5 of body) and honor `If-None-Match` → 304.
- [ ] Append to `api` group in `bootstrap/app.php` + `throttle:120,1` on `/api`.
- [ ] Tests: assert headers + 304 behavior on one endpoint; assert poller-facing client (axios) still fine (it is — 304 handling is transparent).
- [ ] Commit — `perf(api): cache-control, etag/304 and rate limiting`

---

### Task 10: Real anchor links + breadcrumbs
**Fixes issues:** §6 link graph. **Impact:** Medium · **Effort:** M

- [ ] Convert click-navigation to `<router-link>` (renders `<a href>`): `MatchCard.vue` (wrap card), `StandingsTable.vue` rows' team cell, `TopScorersList.vue`, competition tiles. Preserve current styling (`display:block`/`contents`).
- [ ] Create `Breadcrumbs.vue` (Home → Competition → Match etc.), used on the four detail pages; server-side `BreadcrumbList` JSON-LD already shipped in Task 2.
- [ ] Verify with `npm run lint` + manual click-through; commit.

---

### Task 11: Crawlable date pages
**Fixes issues:** §7 daily-fixtures opportunity. **Impact:** Medium · **Effort:** M

- [ ] Add SPA route `/matches/:date(\\d{4}-\\d{2}-\\d{2})` → Matches.vue with date prop (DateNavigator pushes route instead of local state, back/forward works — UX win too).
- [ ] Server route in the SEO shell with per-date title (`Football Matches on 11 June 2026 — Live Scores & Results | LiveGoal`), canonical, and `ItemList` JSON-LD from the cached day feed; sitemap includes ±7 days rolling.
- [ ] Tests + commit.

---

### Task 12 (Phase 2): Content layer — FAQ/glossary/trust pages
**Fixes issues:** #19, §9, §11. **Impact:** High (compounding) · **Effort:** L

- [ ] Markdown-driven static content: `resources/content/guides/*.md` rendered through a `GuideController` + blade (server-rendered HTML — these pages should NOT be SPA views), `FAQPage`/`Article` JSON-LD, listed in sitemap. Start with 10 pages from Pillar 4 + trust set (About, Data sources, Contact). Cross-link from UI ("?" affordances) and footer.
- [ ] `public/llms.txt` describing the site + API surface for AI crawlers.
- [ ] **Needs your approval (new base folder `resources/content/`) + copy review.**

---

### Task 13 (Phase 2): PWA manifest + install
**Fixes issues:** #16, §10. **Impact:** Medium-high retention · **Effort:** M

- [ ] `public/site.webmanifest` (name, icons incl. 512px maskable — needs one new icon asset), `<link rel="manifest">`, iOS meta. Defer service-worker/offline + push (Phase 3 decision — push needs infra).

---

### Task 14 (Phase 3, optional/decision): OG share images per match
**Impact:** Medium · **Effort:** L — dynamic 1200×630 score-card PNG endpoint (`/og/match/{id}.png`, server-generated via GD/Intervention from cached data, cached 60s). Flagged as a decision: adds an image-generation dependency.

---

## Decisions needed from you before implementation

1. **Production domain** — required for canonical/sitemap/OG absolute URLs (`APP_URL` today defaults to localhost). What is it?
2. **Fonts self-hosting** — OK to add `@fontsource/*` npm packages? (CLAUDE.md requires approval for dependency changes.)
3. **Analytics provider** — Plausible, Umami, GA4, or skip for now?
4. **Slug language** — English team short-names from API ok? (e.g. `man-city`, from `tla`/`shortName`.)
5. **Phase 2 content** — am I writing the guide copy, or do you want to provide/review it?
6. **OG image generation (Task 14)** — in or out of scope?

## Suggested execution order

P0 (this week, WC starts tomorrow): Task 1 → 2 → 3 → 4 → 5 → 6 → 7 — ships on one branch `phase/seo-0-crawlable-shell` or split 1-3 / 4-7 into two PRs to `dev`.
P1: Tasks 8 → 9 → 10 → 11. P2: 12 → 13. P3: 14 + push notifications + i18n groundwork.
