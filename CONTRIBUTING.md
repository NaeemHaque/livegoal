# Contributing to LiveGoal

Thanks for your interest in LiveGoal. This guide covers how the project is built, the conventions to
follow, and how to get a change merged.

LiveGoal is a **non-betting** football live-scores app. Contributions that add odds, tips, paid
predictions, or affiliate/gambling content are out of scope and will be declined.

## Prerequisites

- **PHP 8.4+** and **Composer**
- **Node 22+** and **npm**
- A free [football-data.org](https://www.football-data.org/client/register) API token
- Optional but recommended: [Laravel Herd](https://herd.laravel.com) (serves the app at `http://livegoal.test`)

## Getting started

```bash
git clone git@github.com:NaeemHaque/livegoal.git
cd livegoal
composer setup          # install deps, copy .env, generate key, migrate, build assets

# add your token to .env
#   FOOTBALL_DATA_TOKEN=xxxxxxxx

composer dev            # php artisan serve + pail (logs) + scheduler + vite
```

The `composer setup` post-install step also points Git at the version-controlled hooks in `.githooks/`,
so the quality gate runs automatically on commit and push.

## Project layout

```
app/
  Console/Commands   Artisan commands (the live poller, feed warmers, dev helpers)
  Http               Controllers (thin), FormRequests, middleware, API Resources
  Services/Football  FootballData client, Normalizer, FeaturedMatches aggregator
  Seo                Server-rendered crawlable shell
resources/js/
  pages              Route-level Vue screens
  components         Reusable UI
  composables        Data fetching + polling (useDayMatches, useUpcoming, …)
  stores             Pinia stores (matches, settings, favorites)
  router             Vue Router (history mode)
resources/css        Tailwind v4 + design tokens
docs/                Architecture, API, data model, design, decisions, deploy
```

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the request flow and the SPA ↔ API boundary.

## Branching & pull requests

- **`dev`** is the integration branch — **all** PRs target `dev`.
- **`main`** is stable; `dev → main` happens only for a release.
- Branch from the latest `dev` using a descriptive prefix, e.g. `fix/...`, `improve/...`, `feat/...`
  (build phases historically used `phase/<n>-<slug>` — see [`docs/PR_PLAN.md`](docs/PR_PLAN.md)).

```bash
git switch dev && git pull
git switch -c improve/your-change
# …work…
git push -u origin improve/your-change   # opens a PR to dev
```

Open the PR against `dev` using the [pull request template](.github/pull_request_template.md). Keep the
description focused: what changed, why, and how to test. Commit messages are **sentence-case, imperative,
and concise** (e.g. _"Show the competition area flag in the detail header"_) — no conventional-commit
prefixes, and no AI/assistant attribution in commits or PRs.

## Conventions

These are enforced by `composer ci:check` and the Git hooks — match them before pushing.

### PHP

- **Pint** (`laravel` preset) — run `vendor/bin/pint --dirty` before finishing.
- **PHPStan via Larastan at level `max`** — `composer analyse` must pass with no new errors. Do not silence
  errors with `@phpstan-ignore`, baseline entries, casts, or inline `@var`; fix the underlying type.
- Use PHP 8 features: constructor property promotion, explicit return types and parameter type hints,
  enums in `TitleCase`. Prefer PHPDoc array shapes over loose `array`.
- **Thin controllers** — validation in FormRequests, business logic in services, output via Eloquent API
  Resources. Use named routes (`route()`), descriptive names (`isRegisteredForDiscounts`, not `discount()`).

### Frontend

- **Plain JavaScript** Vue 3 with `<script setup>` — there is no TypeScript / `vue-tsc` step.
- **ESLint** + **Prettier** — `npm run lint` and `npm run format`. State lives in Pinia stores; data
  fetching lives in composables; styling uses Tailwind v4 + the design tokens in `resources/css`.

### Tests

- **PHPUnit** (not Pest). Scaffold with `php artisan make:test --phpunit {name}`; prefer feature tests and
  model factories. Cover happy paths, failure paths, and edge cases.
- Every change should be programmatically tested. Run the focused test while iterating, then the suite:

  ```bash
  php artisan test --compact --filter=testName    # one test
  php artisan test --compact                       # full suite
  ```

- Tests run in-memory (SQLite) with array cache and sync queue — no external services needed.

## The quality gate

Before pushing, the full gate must be green:

```bash
composer ci:check       # ESLint + Prettier + PHPStan (max) + Pint + PHPUnit
```

- **`pre-commit`** runs Pint, ESLint, and Prettier on staged files.
- **`pre-push`** runs PHPStan, ESLint, and the test suite.
- **CI** (`.github/workflows/pr-checks.yml`) re-runs all of it on every PR to `dev`/`main`, plus a
  `composer audit` security pass and a PHP 8.4/8.5 test matrix.

Bypass hooks with `--no-verify` only when truly necessary.

## Dependencies & secrets

- Don't add or change dependencies without discussion, and don't introduce excluded realtime tooling
  (Pusher/Reverb/Echo/socket.io/SSE/Redis) — v1 is poll-only by design.
- Never commit secrets. The football-data token stays in `.env` (server-side only) and is never exposed to
  the browser.

## Reporting bugs & security

- **Bugs / features:** open a GitHub issue with steps to reproduce.
- **Security:** do **not** open a public issue — follow [SECURITY.md](SECURITY.md).

## Releasing (maintainers)

1. Ensure `dev` is green and `CHANGELOG.md` has the changes under `[Unreleased]`.
2. Move `[Unreleased]` to a new version section and date it; bump the version.
3. Open a release PR `dev → main` titled `Release — vX.Y.Z`.
4. After merge, tag `main`: `git tag vX.Y.Z && git push origin vX.Y.Z`.
5. Deploy per [`docs/DEPLOY.md`](docs/DEPLOY.md).
