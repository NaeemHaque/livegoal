# livegoal AI Workflow

Adapted from the FluentMembers AI workflow, tuned for this Laravel 13 + Inertia v3 + Vue 3 app. **No `prompt-task` entry point** — agents are invoked directly or chain automatically.

## Development Flow

```
                ┌──────────────┐
   you describe │   engineer   │  writes code (Laravel / Inertia / Vue)
   a task  ───► │   (agent)    │  loads laravel-best-practices + area skills
                └──────┬───────┘
                       │ auto-triggers (when invoked directly)
                       ▼
                ┌──────────────┐
                │   reviewer   │  5 dimensions, severity-labeled
                │   (agent)    │  Security · Conventions · Architecture · Perf · Frontend
                └──────┬───────┘
                  passes? ── no ──► back to engineer (max 3 cycles)
                       │ yes
                       ▼
                ┌──────────────┐
                │ test-writer  │  PHPUnit feature/unit tests, runs them
                │   (agent)    │  loads test-runner skill
                └──────┬───────┘
                  green? ── no ──► back to engineer (max 3 cycles)
                       │ yes
                       ▼
                  /pr-description ──► PR opened (only if you ask)
```

## Agents (`.claude/agents/`)

| Agent | Role | Trigger |
|---|---|---|
| `engineer` | Implements features/fixes/refactors | You describe a task, or routed to it |
| `reviewer` | Reviews code (in-session / current-changes / branch / PR) | Auto after engineer; or "review my changes" / "review PR #N" |
| `test-writer` | Writes + runs PHPUnit tests | Auto after review passes; or "write tests for X" |

## Skills (`.claude/skills/` + Boost-installed)

| Skill | Purpose |
|---|---|
| `test-runner` | PHPUnit patterns: factories, RefreshDatabase, Inertia/HTTP assertions, running tests |
| `pr-description` | Generates PR title + body from the repo template; opens with `gh` on request |
| `debugger` | Reproduce → Find → Verify → Fix root-cause loop |
| `laravel-best-practices` | Laravel/PHP conventions, security, query performance (Boost) |
| `inertia-vue-development` | Inertia v3 + Vue 3 pages, forms, navigation (Boost) |
| `tailwindcss-development` | Tailwind v4 styling (Boost) |
| `wayfinder-development` | Typed route/action helpers for frontend↔backend (Boost) |

## Hooks (`.claude/hooks/`, wired in `.claude/settings.json`)

| Hook | Event | What it does |
|---|---|---|
| `bash_policy.py` | PreToolUse · Bash | Denies destructive commands; asks before sensitive ones (push, force, composer/npm installs, `migrate:fresh`, `rm -rf`, sudo…) |
| `write_guard.py` | PreToolUse · Write/Edit | Asks before writing outside the project root |
| `php-checks.sh` | PostToolUse · Write/Edit | `php -l` syntax check (blocking) + Pint style note (informational) |
| `frontend-checks.sh` | PostToolUse · Write/Edit | Prettier check on `resources/` files (informational) |

These complement the **git hooks** (`.githooks/pre-commit`, `pre-push`) and **CI** (`.github/workflows/pr-checks.yml`).

## Quick Reference

| Scenario | How |
|---|---|
| Build a feature | Describe it → engineer → reviewer → test-writer |
| Quick fix | Describe it → engineer (it chains review + tests) |
| Review only | "review my changes" / "review current branch" / "review PR #N" → reviewer |
| Just tests | "write tests for `<Class::method>`" → test-writer |
| Debug | Invoke the `debugger` skill |
| Open a PR | Invoke the `pr-description` skill |

## Conventions

1. **Be specific** — "Fix the bug" is too vague. State expected vs actual behavior.
2. **Reference, don't describe** — paste file paths, error strings, PR links.
3. **Acceptance criteria are testable** — "returns 200 with the updated record", not "it works".
4. **Review + tests are the default**, not optional, for any code change.
5. **The gate must be green** — `composer ci:check` (Pint, PHPStan max, ESLint, Prettier, vue-tsc, tests) before pushing.
