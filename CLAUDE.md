# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**SocPlay** is a free, non-betting **football live-scores** web app (World Cup 2026–aware + major leagues): live scores, fixtures, results, standings/groups, knockout brackets, competitions, teams. It's a single Laravel app that serves a **decoupled Vue 3 SPA** and a **cached JSON `/api`** from one origin; a single scheduled poller hits football-data.org (free tier) so the browser never does. "Realtime" is polling.

**Authoritative spec:** [`docs/BUILD_PROMPT.md`](docs/BUILD_PROMPT.md). **Plan + design + decisions:** [`docs/PLAN.md`](docs/PLAN.md) and the docs it references (`ARCHITECTURE`, `API`, `DATA_MODEL`, `DESIGN`, `LIVE_POLLING`, `DECISIONS`). Read those before building.

> **Architecture transition (in progress).** The repo started as the `laravel/blank-vue-starter-kit` (**Inertia v3 + Wayfinder + TypeScript**). Per [`docs/DECISIONS.md`](docs/DECISIONS.md), the target is a **Vue Router + Pinia + axios SPA in plain JavaScript** consuming `/api` JSON — **Inertia and Wayfinder are being removed in Phase 0**, and TypeScript (`vue-tsc`) is dropped from the gate. Until Phase 0 lands, parts of the "Architecture" section below still describe the Inertia scaffold; trust `docs/` for the target.

## Commands

Run these from the repo root.

| Task | Command |
| --- | --- |
| First-time setup | `composer setup` (install deps, copy `.env`, key, migrate, build) |
| Run everything for local dev | `composer dev` — runs `php artisan serve`, `queue:listen`, `pail` (logs), and `vite` concurrently |
| Frontend dev server only | `npm run dev` |
| Production asset build | `npm run build` (or `npm run build:ssr` for SSR bundle) |
| All PHP tests | `php artisan test --compact` |
| Single test file | `php artisan test --compact tests/Feature/ExampleTest.php` |
| Single test by name | `php artisan test --compact --filter=testMethodName` |
| Static analysis | `composer analyse` (PHPStan/Larastan, level `max` — config in `phpstan.neon`) |
| Format PHP (do this before finishing) | `vendor/bin/pint --dirty --format agent` |
| Lint/format JS | `npm run lint` · `npm run format` (plain JS — no type-check step) |
| Full CI-equivalent gate | `composer ci:check` (JS lint + format + PHPStan + Pint + tests) |

The site is also served by Laravel Herd; use the `get-absolute-url` Boost tool to resolve URLs rather than constructing them.

## Architecture

**Request flow.** A browser request hits a route in `routes/web.php`, which either renders an Inertia page directly (`Route::inertia('/', 'Welcome')`) or returns `Inertia::render('Page', $props)` from a controller. `HandleInertiaRequests` (registered as global middleware) wraps the response and injects shared props.

**Shared Inertia props** live in `App\Http\Middleware\HandleInertiaRequests::share()` — currently `name` and `auth.user`. Anything every page needs (auth user, flash, config) belongs here, not in individual controllers. The matching TypeScript shapes are hand-maintained in `resources/js/types/` (`auth.ts`, etc.).

**Wayfinder bridges PHP routes → typed TS.** The `@laravel/vite-plugin-wayfinder` vite plugin regenerates typed route/action helpers on build into `resources/js/routes/` (named routes) and `resources/js/actions/` (controller actions). **These files are generated — never hand-edit them.** Import them in Vue code (`import { home } from '@/routes'`) and call `.url()` / `.get()` / `.post()` / `.form()` instead of hardcoding paths. After adding or renaming a backend route, the helpers refresh on the next vite build/dev run (or `php artisan wayfinder:generate`). Activate the `wayfinder-development` skill when wiring frontend calls to backend routes.

**Frontend.** Entry point is `resources/js/app.ts` (`createInertiaApp`). Pages are `.vue` files under `resources/js/pages/` and are referenced by name from the server (`'Welcome'` → `resources/js/pages/Welcome.vue`); `app.blade.php` lazily `@vite`s the active page component. The `@/*` path alias maps to `resources/js/*`. Styling is Tailwind CSS v4 via the vite plugin (`resources/css/app.css`); compose class names with `cn()` from `@/lib/utils`. SSR runs automatically in vite dev via `@inertiajs/vite`.

**Persistence.** Default connection is SQLite at `database/database.sqlite`. Tests run against an in-memory SQLite DB (see `phpunit.xml`), with cache=array, queue=sync, mail=array — so tests are isolated and need no external services.

## Conventions specific to this repo

- **Pint preset is `laravel`** (`pint.json`); always run `vendor/bin/pint --dirty --format agent` after touching PHP.
- **Static analysis is PHPStan via Larastan** (`phpstan.neon`, level `max`, analyses `app/`). Run `composer analyse` before pushing; keep new code passing at max strictness.
- **PHPUnit, not Pest** — tests are plain PHPUnit classes under `tests/Feature` and `tests/Unit`. Scaffold with `php artisan make:test --phpunit {name}`.
- **Git hooks live in `.githooks/`** (version-controlled) and are enabled via `core.hooksPath`, set automatically by the composer `post-install-cmd`. `pre-commit` runs Pint + ESLint + Prettier on staged files; `pre-push` runs PHPStan + ESLint + the test suite. Bypass with `--no-verify` only when necessary.
- **CI** is a single workflow, `.github/workflows/pr-checks.yml`, triggered on pull requests to `dev` and `main`. It has three jobs: `code-quality` (Pint, PHPStan, ESLint, Prettier), `tests` (PHP 8.4/8.5 matrix, builds assets then runs the suite), and `security` (`composer audit`). A `pull_request_template.md` accompanies it.
- **Branching / PRs** — `dev` is the integration root; each build phase ships on its own `phase/<n>-<slug>` branch and is merged to `dev` via PR. `dev → main` is the release PR. See [`docs/PR_PLAN.md`](docs/PR_PLAN.md).
- **Laravel Boost MCP** is configured in `.mcp.json` (`php artisan boost:mcp`). Prefer Boost tools (`search-docs`, `database-schema`, `database-query`, `browser-logs`) over manual shell equivalents — see the Laravel Boost section below.
- `AGENTS.md` is kept byte-identical to the Boost-guidelines portion of this file; if you regenerate Boost guidelines, both files update together.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
