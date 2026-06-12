# LiveGoal

[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![Tailwind CSS v4](https://img.shields.io/badge/Tailwind-v4-06B6D4?logo=tailwindcss&logoColor=white)

A free, **non-betting** football (soccer) live-scores web app — World Cup 2026–aware, covering major
leagues (Premier League, Champions League, La Liga, Serie A, Bundesliga, Ligue 1, and more). Live
scores, fixtures, results, standings/groups, knockout brackets, competitions, and teams.

> Pure live-scores product: **no odds, tips, predictions-for-money, or affiliate/gambling content.**

## Features

- **Live scores** — a goal toast + score-flip animation, driven by a single server-side poller (not the browser).
- **Fixtures & results** — browse any day with a date navigator; the default view is the full upcoming list.
- **Standings & groups** — league tables and World Cup group tables.
- **Knockout brackets** — drawn round-by-round with connectors and kickoff times.
- **Competitions** — list + per-competition detail (fixtures, results, knockout, top scorers, teams) with real emblems.
- **Teams, players & top scorers** — squads, team fixtures/results, and the golden-boot race.
- **Following** — favourite teams, competitions, and matches (stored locally).
- **Settings** — dark/light theme, 12h/24h time format, auto-refresh interval, reduced motion.
- **SEO-ready** — a crawlable, server-rendered shell and shareable per-date fixtures pages (`/matches/{date}`).

## Stack

- **Backend:** Laravel 13 (PHP 8.4) — a cached JSON API under `/api` + a single scheduled poller against
  [football-data.org](https://www.football-data.org) (free tier). The browser never calls the upstream API.
- **Frontend:** Vue 3 SPA (plain JS, `<script setup>`) — Vite, Vue Router (history mode), Pinia,
  `@vueuse/core`, axios, Tailwind CSS v4. Served by Laravel via a catch-all route (one origin, no CORS).
- "Realtime" is **polling** (free-tier data is delayed and poll-only), so there are no websockets in v1.

## Quickstart

```bash
# One-shot: install deps, copy .env, generate key, migrate, build assets
composer setup

# Add your free football-data.org token to .env:
#   FOOTBALL_DATA_TOKEN=xxxxxxxx   (register at https://www.football-data.org/client/register)

composer dev           # php artisan serve + pail (logs) + scheduler + vite
```

Served by **Laravel Herd** at `http://livegoal.test`, or `http://127.0.0.1:8000` via `php artisan serve`.

> First run without Herd: `php artisan serve` and `npm run dev` (or `npm run build`) in separate shells.

## Commands

| Task | Command |
| --- | --- |
| First-time setup | `composer setup` |
| Run everything (server, logs, scheduler, vite) | `composer dev` |
| Frontend dev server only | `npm run dev` |
| Production asset build | `npm run build` |
| Full CI-equivalent gate | `composer ci:check` |
| Static analysis (PHPStan/Larastan, max) | `composer analyse` |
| Format PHP | `vendor/bin/pint --dirty` |
| Lint / format JS | `npm run lint` · `npm run format` |
| Run tests | `php artisan test --compact` |
| Single test | `php artisan test --compact --filter=testName` |

## Environment

| Var | Purpose |
| --- | --- |
| `FOOTBALL_DATA_TOKEN` | football-data.org API token (server-side only — never shipped to the browser). |
| `CACHE_STORE` | `database` (default) or `file` — the poller and `/api` cache live here. |
| `SCHEDULER_TOKEN` | Optional. Enables the token-guarded `GET /scheduler/run` endpoint for hosts without system cron. |
| `APP_URL` | App URL. Local: `http://livegoal.test`. Production: your domain. |

## Live scores (the poller)

"Realtime" is delivered by a single scheduled poller, not websockets — the free data source is itself
delayed and poll-only (see [`docs/LIVE_POLLING.md`](docs/LIVE_POLLING.md)).

- `php artisan app:poll-live-scores` fetches all in-play/paused matches in one upstream request and writes
  them to cache (`GET /api/live` serves that cache). It is scheduled **every minute**.
- **Cron** — one line keeps the whole site live:
  ```bash
  * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
  ```
- **No system cron?** Set `SCHEDULER_TOKEN` and point a free pinger (e.g. [cron-job.org](https://cron-job.org))
  at `GET /scheduler/run?token=<SCHEDULER_TOKEN>` every minute. The route 404s when the token is empty/wrong.

## Free-tier caveats

Scores are delayed a few minutes and the free tier has **no lineups, match events, detailed match stats, or
detailed player stats**. The UI surfaces an "updated Xs ago" timestamp near the LIVE badge and renders those
data-poor panels as an intentional "Not available on this data plan" state — never blank.

## Quality & tests

```bash
composer ci:check      # ESLint + Prettier + PHPStan (max) + Pint + PHPUnit
```

Git hooks (in `.githooks/`, enabled automatically on `composer install`) run these on commit/push, and the
same checks run in CI for every PR (`.github/workflows/pr-checks.yml`).

## Documentation

| Doc | What's in it |
| --- | --- |
| [`docs/PLAN.md`](docs/PLAN.md) | The build plan and the index for everything below. |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Request flow, caching, the SPA ↔ API boundary. |
| [`docs/API.md`](docs/API.md) | The `/api` endpoints and response shapes. |
| [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md) | Normalised match/competition/team shapes. |
| [`docs/LIVE_POLLING.md`](docs/LIVE_POLLING.md) | The poller, caching, and the future broadcast hook. |
| [`docs/DESIGN.md`](docs/DESIGN.md) | Visual language and tokens. |
| [`docs/DECISIONS.md`](docs/DECISIONS.md) | Architecture decisions and trade-offs. |
| [`docs/DEPLOY.md`](docs/DEPLOY.md) | Step-by-step production deployment. |

## Contributing

`dev` is the integration branch; work ships on its own branch and merges into `dev` via PR. See
**[CONTRIBUTING.md](CONTRIBUTING.md)** for the branching model, conventions, and the quality gate.

## Deployment

One Laravel host serves the SPA, the `/api`, and the poller — no separate frontend host, websocket server, or
external queue. Production needs only PHP 8.4+, the built assets, and **one** of: a system-cron line or a
token-guarded `/scheduler/run` pinger to drive the every-minute poller.

Full guide (env, web server + SPA fallback, HTTPS, cron / cron-job.org, Laravel Cloud): **[`docs/DEPLOY.md`](docs/DEPLOY.md)**.

## Security

Found a vulnerability? Please follow the disclosure process in **[SECURITY.md](SECURITY.md)** rather than
opening a public issue.

## Author

Built and maintained by **Naeem Haque** — [@NaeemHaque](https://github.com/NaeemHaque).

## License

[MIT](LICENSE).
