# Changelog

All notable changes to LiveGoal are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims to follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The first tagged release (`v1.0.0`) will be cut when `dev` is merged to `main` at go-live; everything built
so far lives under **Unreleased**.

## [Unreleased]

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

### Changed

- The Matches page defaults to the **full upcoming list**; picking a date (including today) filters to that day.

### Notes

- v1 is **poll-only** — no websockets/Pusher/Reverb/Echo/SSE/Redis. The poller has one commented
  `broadcast(...)` extension point for a future push upgrade.

[Unreleased]: https://github.com/NaeemHaque/livegoal/commits/dev
