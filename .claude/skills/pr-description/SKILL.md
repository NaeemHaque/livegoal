---
name: pr-description
description: Generate a clear PR title and body for socplay changes, aligned with the repo's pull request template, and optionally open the PR with gh. Use when finishing a change, preparing to open a pull request, or when asked to write a PR description/summary.
---

# pr-description

Produce a PR **title** and **body** for the current branch, matching `.github/pull_request_template.md`, then (if asked) open it with `gh`.

## Step 1 — Gather the change

```bash
git branch --show-current
git log dev..HEAD --oneline        # commits on this branch
git diff dev...HEAD --stat         # files touched
git diff dev...HEAD                 # full diff (read for the summary)
```

The base branch is **`dev`** — phase branches target `dev` (see `docs/PR_PLAN.md`). `dev → main` PRs are release PRs.

## Step 2 — Write the title

- Imperative mood, concise, no trailing period: `Add profile update page`, `Fix 419 on settings form`.
- Prefix with a scope only if the repo's existing PR titles do.

## Step 3 — Write the body (fill the repo template)

Use the sections from `.github/pull_request_template.md`:

1. **What does this PR do and why?** — 1–3 sentences: the change and the problem it solves. Link issues (`Fixes #123`).
2. **Phase** — name the phase if this is a phase PR (e.g. "Phase 2 — Backend API").
3. **Changes** — check the relevant boxes (PHP / Vue SPA / CSS-Tailwind / Database / JSON API / Build-config) and bullet the concrete changes.
3. **How to test** — specific, reproducible steps (which page, what input, expected result).
4. **Screenshots** — include for UI changes; otherwise remove the section.
5. **Checklist** — confirm `composer ci:check` passes and tests were added/updated.
6. **Anything the reviewer should know?** — edge cases, trade-offs, areas wanting scrutiny.

Ground every claim in the actual diff — never invent changes that aren't there. Keep it scannable: short paragraphs and bullets.

## Step 4 — Open the PR (only if asked)

```bash
gh pr create --base dev --title "<title>" --body "<body>"
```

For drafts add `--draft`. Confirm the base branch with the user before creating if there's any doubt. Do not push or open a PR unless the user asked.

## Notes

- Do not add a co-author trailer to commits or PR bodies for this repo.
- Keep the summary honest: if tests were skipped or something is incomplete, say so under "Anything the reviewer should know?".
