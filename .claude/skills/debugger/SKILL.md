---
name: debugger
description: Systematic root-cause debugging for the socplay Laravel + Inertia + Vue app — a Reproduce → Find → Verify → Fix loop that uses logs, errors, tests, and tinker before proposing changes. Use when investigating a bug, test failure, exception, 419/500 error, unexpected behavior, or any "why is this happening" question.
---

# debugger

A disciplined loop for finding the **root cause** before changing code. Never patch a symptom you can't explain.

## Loop

### 1. Reproduce
- Restate the expected vs actual behavior in one line each.
- Find the smallest reliable trigger: a failing test (`php artisan test --filter=...`), a specific request, or a tinker snippet.
- If there's no test that reproduces it, write one first — it becomes the proof of the fix.

### 2. Gather evidence
Use the Laravel Boost MCP tools and logs before guessing:
- `last-error` — the most recent exception.
- `read-log-entries` / `php artisan pail` — application log stream.
- `browser-logs` — frontend/console errors for Inertia/Vue issues.
- `database-query` / `database-schema` — inspect actual data and structure.
- `php artisan tinker --execute '...'` — probe runtime state (single-quote the code).
- For frontend: check the Network tab / Inertia response, and `npm run types:check`.

### 3. Find (form hypotheses)
- Read the failing code path top to bottom: route → middleware → controller → FormRequest → service → model → view/props.
- List 2–3 concrete hypotheses ranked by likelihood. For each, name the single observation that would confirm or kill it.
- Common Laravel/Inertia culprits: missing/incorrect validation, authorization throwing, mass-assignment guarded, N+1 or wrong relationship, missing eager load, CSRF/session (419), stale Vite build, missing shared prop, Wayfinder helper out of date.

### 4. Verify (prove the cause)
- Confirm the hypothesis with a direct observation (a log line, a query result, a tinker value, an asserted intermediate state) — not by reasoning alone.
- Do not proceed to a fix until exactly one hypothesis is confirmed. If none hold, gather more evidence.

### 5. Fix
- Make the **minimal** change that addresses the confirmed root cause. Follow project conventions (load `laravel-best-practices`).
- Re-run the reproducing test and the surrounding suite (`php artisan test --compact`).
- Run `vendor/bin/pint --dirty` and `composer analyse` so the fix passes the gate.

## Output (debugger report)

```
## Debugger Report — <one-line title>

**Symptom**: expected … / actual …
**Reproduction**: <test, request, or snippet>

### Root cause
<the confirmed cause, with the evidence that proved it — file:line, log line, or query result>

### Fix
<what changed and why it resolves the cause>

### Verification
- [x] Reproducing test now passes
- [x] Surrounding suite green
- [x] Pint + PHPStan clean
```

## Rules

- Evidence before assertions — never claim a cause you haven't observed.
- One root cause at a time. If you find several issues, note them, fix the one in scope, and surface the rest.
- Don't disable tests or swallow errors to make symptoms disappear.
