# Changelog

All notable changes to LiveGoal are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims to follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Releases are tagged on the `dev` line and deployed from there.

## [Unreleased]

## [1.1.0] - 2026-06-16

### Added

- **ESPN live data** on match detail: a real match clock and official event minutes (goal scorer + assist,
  yellow/red cards, substitutions), sourced from ESPN's keyless feed with football-data.org kept as the
  fallback. New endpoint `GET /api/matches/{id}/espn` overlays the live timeline and the header clock.

### Changed

- **Matches page** sections are now collapsible, and the cross-day upcoming list reveals three days at a time
  ("Load more days") instead of dumping the whole schedule — far less scrolling, especially on mobile.
- **Live Hub** fixtures are grouped under a competition header with Upcoming/Finished tabs; finished cards show
  their date.
- Replaced **Pinia** with native `reactive()` singleton stores — one fewer dependency, identical behaviour.

### Fixed

- Cleared the `apple-mobile-web-app-capable` console deprecation warning by adding the standard
  `mobile-web-app-capable` meta tag (the Apple alias is kept for iOS install).

## [1.0.2] - 2026-06-13

### Added

- **Web push match alerts** — goal and full-time notifications for followed teams and competitions, via the
  Web Push API + VAPID with anonymous subscriptions: a service worker, a subscription API, a PWA manifest, and
  iOS install metadata. Pushes are suppressed while a LiveGoal tab is visible (the in-app toast covers that
  case). Requires a running queue worker on the server.

### Fixed

- Live **clock drift and goal latency**: harvest the recorded score and anchor the match clock on the
  kickoff/half-time whistle so the displayed minute tracks the real match instead of running minutes ahead.
- Match timeline away-lane alignment and a single-column mobile layout; immediate SQLite transactions so writes
  respect the busy timeout.

## [1.0.1] - 2026-06-12

### Added

- **Recent results** view, and the poller now overlays finished-match finals onto the aggregate feeds so a
  just-finished match never lingers as scheduled.

## [1.0.0] - 2026-06-11

### Added

- **Live scores** via a single server-side poller (`app:poll-live-scores`, scheduled every minute) that
  caches in-play matches for `GET /api/live` — the browser never hits the upstream API. A client-side goal
  detector drives a "GOAL!" toast and a score-flip animation.
- **Cached JSON API** under `/api` (live, day fixtures, upcoming, competitions, standings, scorers, teams,
  players, search) backed by football-data.org's free tier, with last-good fallback and scheduled feed warming.
- **Vue 3 SPA** (plain JS, Vue Router history mode, Pinia, axios, Tailwind v4) served from one origin:
  - Live Hub, Matches (browse by date), Competitions + detail (fixtures, results, knockout, top scorers, teams),
    Team, Player, Match, Top Scorers, Following, Search, and Settings.
  - Standings/group tables, knockout brackets with drawn connectors and kickoff times, date-grouped fixtures.
  - World Cup 2026 spotlight with the tournament emblem, real competition emblems, and area flags.
  - Following/favourites (teams, competitions, matches) stored locally.
  - Settings: dark/light theme, 12h/24h time format, auto-refresh interval, reduced motion.
- **SEO**: a crawlable, server-rendered shell and shareable per-date fixtures pages (`/matches/{date}`).
- **Token-guarded scheduler endpoint** (`GET /scheduler/run`) for hosts without system cron.
- Tooling: PHPStan (Larastan, level `max`), Pint, ESLint, Prettier, PHPUnit, Git hooks, and CI
  (`pr-checks.yml`) with a PHP 8.4/8.5 matrix and a `composer audit` security pass.

### Notes

- Realtime is **poll-only** — no websockets/Pusher/Reverb/Echo/SSE/Redis. The poller has one commented
  `broadcast(...)` extension point for a future upgrade.

[Unreleased]: https://github.com/NaeemHaque/livegoal/compare/v1.1.0...dev
[1.1.0]: https://github.com/NaeemHaque/livegoal/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/NaeemHaque/livegoal/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/NaeemHaque/livegoal/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/NaeemHaque/livegoal/releases/tag/v1.0.0
