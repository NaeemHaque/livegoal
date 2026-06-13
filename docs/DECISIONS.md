# LiveGoal — Decision Log

Decisions that shape the build, with rationale. Newest first.

## D1 — Architecture: decoupled Vue SPA + Laravel JSON API (remove Inertia/Wayfinder)

**Decision:** Build a standalone Vue 3 SPA (Vue Router history mode + Pinia + axios) that consumes a
Laravel `/api` JSON layer, served by a catch-all web route from one origin. Remove Inertia v3 and
Wayfinder from the starter scaffold.

**Why:** `BUILD_PROMPT` §2 and Appendix A specify exactly this shape, and the rate-limit/caching story
(central poller → cache → browser polls `/api/live`) depends on a browser-facing JSON API. The design
prototype is itself a client-rendered SPA with its own router. User confirmed.

**Impact:** Phase 0 removes `inertiajs/inertia-laravel`, `@inertiajs/*`, `@laravel/vite-plugin-wayfinder`;
adds `vue-router`, `pinia`, `axios`, `@vueuse/core`, `@lucide/vue` (the current Lucide Vue package; `lucide-vue-next` is end-of-lined at 1.0.0). CLAUDE.md, the AI agents/skills
(which assume Inertia), and the Boost guideline package list are reconciled.

## D2 — Frontend language: plain JavaScript (not TypeScript)

**Decision:** Frontend is plain JS (`.js`, `<script setup>` without `lang="ts"`).

**Why:** The spec and design prototype are plain JS; faster 1:1 port. User confirmed.

**Impact:** Drop `vue-tsc` / `npm run types:check` from the CI gate, pre-push hook, and `composer ci:check`;
switch ESLint to a Vue + JS config; add `jsconfig.json` for editor path aliases. Frontend stays
**lint + build** gated. (PHP keeps Pint + PHPStan max + PHPUnit unchanged.)

## D3 — Navigation: top horizontal nav (not the build prompt's "sidebar")

**Decision:** Desktop uses a top horizontal nav (Sofascore-style) + mobile bottom tab bar. No left
sidebar.

**Why:** `BUILD_PROMPT` Phase 1 text says "desktop sidebar nav," but the **design is the final output**
and the chat transcript shows the user explicitly rejected the sidebar ("this is sofascore. the menu is
not in left"). The exported `shell.jsx` ships the top-nav layout. Design wins over the older prompt text.

## D4 — CSS namespace: keep the design's `pp-` class prefix

**Decision:** Keep `pp-*` class names from the design when porting `components.css`/`app.css`.

**Why:** Lets us reuse the design CSS verbatim for pixel fidelity. The prefix is an internal artifact of
the prototype's original name ("PitchPulse" → "LiveGoal"); class names aren't user-visible. Lower porting
risk than a rename.

## D5 — Keep Laravel 13 (spec says "11/12")

**Decision:** Stay on the installed Laravel 13.

**Why:** 13 is a superset; the spec's "11/12" is a loose floor. No reason to downgrade.

## D6 — Testing scope

**Decision:** PHPUnit feature tests cover the API/service/poller (cache hits, 429 last-good, standings
group parsing, score-diff). No JS unit-test framework added in v1; frontend is lint + build gated.

**Why:** Matches the project's "bare minimum" ethos and the spec's Definition of Done (clean build,
ESLint/Prettier clean, backend boots, poller works). Revisit if the user wants component tests.

## D7 — Local-first; defer domain go-live

**Decision:** Develop and test entirely **locally** (Laravel Herd at `http://livegoal.test`, `APP_URL`
stays local). Purchase `livegoal.win` and run the go-live steps (DNS, HTTPS, `APP_URL`, SPA fallback) only
**after the project is feature-complete**.

**Why:** User direction — test locally now, buy the domain and deploy when ready. Keeps everything
host-agnostic per `BUILD_PROMPT` Appendix C; no cost until launch.

## D8 — Web Push for match alerts (no third-party push service)

**Decision:** Goal/full-time alerts for followed teams & competitions ship as **Web Push API +
self-generated VAPID keys** through Laravel's notifications framework with the MIT
`laravel-notification-channels/webpush` channel. Subscriptions stay **anonymous** (no user accounts):
one `push_subscribers` row per browser endpoint carrying its own follow snapshot in a queryable
`push_follows` pivot (not JSON — SQLite can't index JSON containment). Pushes dispatch from the live
poller at the exact points where GOAL/FT timeline events are appended, inheriting every existing
flap/dedupe guard. Full design: `docs/PUSH_NOTIFICATIONS.md`.

**Why:** $0 forever and no vendor: browser push services are part of the web platform, VAPID is
self-signed, the package is open source. FCM-direct/OneSignal/Pusher Beams add accounts and lock-in
for nothing (the Pusher family is excluded by the build rules anyway). Dispatching at the
timeline-append points avoids inventing a second dedupe layer for an upstream that flaps scores.
Rejected: user accounts (overkill for a bookmark-style follow feature), JSON follow storage
(unindexable in SQLite), SSE/WebSockets (excluded; can't reach closed tabs anyway).

## D9 — Frontend state: native reactive singletons (remove Pinia)

**Decision:** Drop **Pinia**. Shared client state lives in module-level `reactive()` singletons under
`resources/js/stores/` (`settings`, `favorites`, `matches`), each exposed through an unchanged
`useXStore()` accessor. Supersedes the Pinia choice in D1.

**Why:** The app's shared state is three small stores over simple data (the live feed, user prefs,
favorites). Pinia's value-add — devtools, plugins, SSR-safe per-request instancing — buys nothing in a
client-only SPA this size, so native Vue reactivity is the right-sized tool: one fewer dependency and no
extra concepts. Vue's own State Management guide endorses `reactive()` singletons for exactly this case.
Ergonomics are unchanged — a Pinia setup store is itself `reactive()` over unwrapped refs, so components
read and write `store.prop` (and `store.method()`) identically. User direction.

**Impact:** Remove `pinia` and `createPinia()` from `resources/js/main.js`. No consumer changes were
needed — nothing used a Pinia-only API (`$patch`, `$reset`, `storeToRefs`, …), so the swap is internal to
the three store modules.

## Resolved inputs

- **`FOOTBALL_DATA_TOKEN`** — ✅ provided and **verified** (2026-06-07): HTTP 200, 13 free competitions,
  standings/crests present, `x-requests-available-minute` confirms the 10/min ceiling. Stored in `.env`
  (gitignored); placeholder added to `.env.example`. Real Phases 2–3 verification can run against live data.

## Pending

- **`livegoal.win`** — not purchased; deferred to launch (see D7).
