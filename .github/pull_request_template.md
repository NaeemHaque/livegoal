## What does this PR do and why?

<!-- What: brief description of the change. Why: the problem or need behind it. Link the issue if one exists: Fixes #123 -->



**Phase:** <!-- e.g. "Phase 2 — Backend API" (see docs/PLAN.md). Remove this line for non-phase PRs. -->

## Changes

<!-- List the concrete changes. Delete any that don't apply. -->

- [ ] PHP — API controllers, FootballData service, poller, config
- [ ] Vue SPA — pages, components, stores, composables
- [ ] CSS / Tailwind / design tokens
- [ ] Database — migrations, factories, seeders
- [ ] JSON API (`/api/*`) or routes
- [ ] Build / config (Vite, composer, CI, hooks)

## How to test

<!--
Steps for the reviewer to verify this works.
Be specific — which route or endpoint, what input, what to expect.
-->

1.

## Screenshots

<!-- For UI changes, paste before/after in BOTH dark and light. Remove this section if not applicable. -->

## Checklist

- [ ] `composer ci:check` passes locally (Pint, PHPStan max, ESLint, Prettier, tests)
- [ ] Phase acceptance criteria met (see `docs/PLAN.md` / `docs/BUILD_PROMPT.md`)
- [ ] No excluded tools added — no Pusher/Reverb/Echo/socket.io/SSE/Redis (only the commented broadcast hook)
- [ ] Secrets stay in `.env` (the football-data token is never committed)
- [ ] Added/updated tests where applicable

## Anything the reviewer should know?

<!-- Edge cases, trade-offs, free-tier data gaps, or areas you'd like extra scrutiny on. Leave blank if nothing comes to mind. -->
