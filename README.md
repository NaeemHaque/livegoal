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

Single-host deploy (PHP + a cron job for the poller) at `socplay.win`. The full deploy guide — domain,
HTTPS, `APP_URL`, cron / cron-job.org fallback, SPA fallback — lands in Phase 6. See
[`docs/BUILD_PROMPT.md`](docs/BUILD_PROMPT.md) §8 and Appendix B–C.
