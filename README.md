# SocPlay

A free, **non-betting** football (soccer) live-scores web app — World Cup 2026–aware, covering major
leagues (Premier League, Champions League, La Liga, Serie A, Bundesliga, Ligue 1, and more). Live
scores, fixtures, results, standings/groups, knockout brackets, competitions, and teams.

> Pure live-scores product: no odds, tips, predictions-for-money, or affiliate/gambling content.

## Stack

- **Backend:** Laravel 13 (PHP 8.4) — a cached JSON API under `/api` + a single scheduled poller against
  [football-data.org](https://www.football-data.org) (free tier). The browser never calls the upstream API.
- **Frontend:** Vue 3 SPA (plain JS, `<script setup>`) — Vite, Vue Router (history mode), Pinia,
  `@vueuse/core`, axios, Tailwind CSS v4. Served by Laravel via a catch-all route (one origin, no CORS).
- "Realtime" is **polling** (free-tier data is delayed and poll-only), so there are no websockets in v1.

Full design + architecture: see [`docs/PLAN.md`](docs/PLAN.md) and the docs it links.

## Quickstart

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# Add your free football-data.org token to .env:
#   FOOTBALL_DATA_TOKEN=xxxxxxxx   (register at https://www.football-data.org/client/register)

npm run build          # or: npm run dev
php artisan serve
```

Or run the backend, logs, and Vite together:

```bash
composer dev           # php artisan serve + pail (logs) + vite
```

Served by **Laravel Herd** at `http://socplay.test`, or `http://127.0.0.1:8000` via `php artisan serve`.

## Environment

| Var | Purpose |
|---|---|
| `FOOTBALL_DATA_TOKEN` | football-data.org API token (server-side only — never shipped to the browser). |
| `CACHE_STORE` | `database` (default) or `file` — the poller and `/api` cache live here. |
| `APP_URL` | App URL. Local: `http://socplay.test`. Production: set at go-live (domain not purchased yet). |

## Free-tier caveats

Scores are delayed a few minutes and the free tier has **no lineups, match events, detailed match
stats, or detailed player stats**. The UI surfaces an "updated Xs ago" timestamp near the LIVE badge and
renders those data-poor panels as an intentional "Not available on this data plan" state — never blank.

## Live scores (the poller)

"Realtime" is delivered by a single scheduled poller, not websockets — the free data source is itself
delayed and poll-only (see [`docs/LIVE_POLLING.md`](docs/LIVE_POLLING.md)).

- `php artisan app:poll-live-scores` fetches all in-play/paused matches in one upstream request and
  writes them to cache (`GET /api/live` serves that cache). It's scheduled **every minute**.
- **Cron** (one line keeps the whole site live):
  ```bash
  * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
  ```
- **No system cron?** Set `SCHEDULER_TOKEN` in `.env` and point free [cron-job.org](https://cron-job.org)
  at `GET /scheduler/run?token=<SCHEDULER_TOKEN>` every minute. The route is token-guarded (404s
  otherwise) and disabled when the token is empty.
- **Future upgrade (not wired in v1):** the poller has one commented `broadcast(...)` extension point.
  Adding Laravel Reverb on a VPS later enables push updates without changing the polling flow — no
  Pusher/Reverb/Echo/SSE/Redis ships today.

## Quality & tests

```bash
composer ci:check      # ESLint + Prettier + PHPStan (max) + Pint + PHPUnit
composer analyse       # PHPStan (Larastan, level max)
php artisan test --compact
npm run lint && npm run format
```

Git hooks (in `.githooks/`, enabled automatically on `composer install`) run these on commit/push.

## Contributing

`dev` is the integration branch. Each build phase ships on its own `phase/<n>-<slug>` branch and merges
into `dev` via a pull request (CI: `.github/workflows/pr-checks.yml`). See [`docs/PR_PLAN.md`](docs/PR_PLAN.md).

## Deployment

One Laravel host serves the SPA, the `/api`, and the poller — no separate frontend host, websocket server, or
external queue. Production needs only PHP 8.4+, the built assets, and **one** of: a system-cron line or a
token-guarded `/scheduler/run` pinger to drive the every-minute poller.

Full step-by-step guide (env, web server + SPA fallback, HTTPS, cron / cron-job.org, Laravel Cloud):
**[`docs/DEPLOY.md`](docs/DEPLOY.md)**.
