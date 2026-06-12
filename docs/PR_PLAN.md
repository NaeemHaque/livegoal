# LiveGoal — Branching & PR Plan

How work flows into the repo. Each build phase is implemented on its own branch and merged into `dev`
via a pull request. Plan: [`PLAN.md`](./PLAN.md) · Spec: [`BUILD_PROMPT.md`](./BUILD_PROMPT.md).

## Branch model

```
main      ← stable / release. dev is merged here only at go-live (release PRs).
  ▲
  │ release PR (dev → main), at launch
  │
dev       ← integration root. ALL phase PRs target this branch.
  ▲ ▲ ▲
  │ │ └── phase/2-backend-api      ──PR──▶ dev
  │ └──── phase/1-foundation       ──PR──▶ dev
  └────── phase/0-scaffold         ──PR──▶ dev
```

- **`dev`** — the targeted root. Every phase branches from the latest `dev` and merges back into it via PR.
- **`main`** — stable. `dev → main` happens only for a release (go-live), per `BUILD_PROMPT` Appendix C.
- **Foundation/setup** (tooling, CI, AI workflow, these docs) lands on `dev` directly — it predates the
  phase work and is the base everything branches from.

## Phase branches

One branch + one PR per phase. Branch naming: **`phase/<n>-<slug>`**.

| Phase | Branch | PR title | Merges to |
|---|---|---|---|
| 0 — Convert scaffold | `phase/0-scaffold` | `Phase 0 — Convert scaffold to Vue SPA + JSON API` | `dev` |
| 1 — Foundation & tooling | `phase/1-foundation` | `Phase 1 — Foundation & app shell` | `dev` |
| 2 — Backend API | `phase/2-backend-api` | `Phase 2 — FootballData service + cached /api` | `dev` |
| 3 — Live polling | `phase/3-live-polling` | `Phase 3 — Live polling engine` | `dev` |
| 4 — Frontend core | `phase/4-frontend-core` | `Phase 4 — Data layer, polling, states` | `dev` |
| 5 — Primary screens | `phase/5-primary-screens` | `Phase 5 — Primary screens` | `dev` |
| 6 — Secondary + ship | `phase/6-secondary-ship` | `Phase 6 — Secondary screens, polish & deploy` | `dev` |
| Release | — | `Release — LiveGoal v1` | `dev → main` |

Phase scope and acceptance criteria are in [`PLAN.md`](./PLAN.md) §7 and `BUILD_PROMPT.md` §8.

## Per-phase workflow

1. **Branch** from the latest `dev`:
   ```bash
   git switch dev && git pull
   git switch -c phase/<n>-<slug>
   ```
2. **Implement** with small, focused **Conventional Commits**, following the phase's commit plan in
   `BUILD_PROMPT.md` §8 (`feat:`, `fix:`, `chore:`, `refactor:`, `docs:`, `perf:`, `build:`). No co-author trailer.
3. **Verify locally** — the phase's acceptance checklist + the full gate:
   ```bash
   composer ci:check        # Pint, PHPStan max, ESLint, Prettier, tests
   npm run build            # SPA builds clean
   ```
   (The `pre-push` git hook runs PHPStan + tests automatically.)
4. **Push & open the PR** to `dev` using the template (the `pr-description` skill drafts it):
   ```bash
   git push -u origin phase/<n>-<slug>
   gh pr create --base dev --title "Phase <n> — <name>" --body "<from template>"
   ```
5. **CI runs** — `pr-checks.yml` triggers on PRs to `dev` (code-quality · tests matrix · security audit).
6. **Review** — the `reviewer` agent (or `/code-review`) reviews the branch vs `dev`; address findings.
7. **Merge** when CI is green and acceptance criteria pass (see merge strategy below).
8. **Gate the next phase** — do not start phase N+1 until phase N is merged to `dev` and its acceptance
   criteria pass (`BUILD_PROMPT` §0.3 / §7).

## PR conventions

- **Target `dev`** (never `main`, except the release PR).
- **Title:** `Phase <n> — <name>` for phase PRs; otherwise a concise imperative summary.
- **Body:** fill `.github/pull_request_template.md` — set the **Phase** line, tick the **Changes** boxes,
  give concrete **How to test** steps, attach **dark + light** screenshots for UI, complete the
  **Checklist** (incl. `composer ci:check` green, acceptance met, no excluded tools, secrets in `.env`).
- **Honesty:** note any free-tier data gaps or skipped items under "Anything the reviewer should know?".

## Merge strategy

- **Phase → `dev`: merge commit** (no squash) to preserve the phase's Conventional Commit history that
  `BUILD_PROMPT` lays out per phase. Delete the phase branch after merge.
- **`dev` → `main`: merge commit** for the release.
- Keep branches rebased/merged up to date with `dev` before merging to avoid drift.

## Gates (must hold before merge)

- CI green on the PR (`pr-checks.yml`).
- `composer ci:check` clean locally; `npm run build` clean.
- Phase acceptance criteria met.
- No excluded realtime tools introduced (`BUILD_PROMPT` §3) — only the commented broadcast hook.
- No secrets committed (`FOOTBALL_DATA_TOKEN` stays in `.env`).
