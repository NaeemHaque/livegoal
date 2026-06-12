# Security Policy

## Supported versions

LiveGoal is pre-1.0 and ships from `main`. Security fixes are applied to the latest `main` only.

| Version | Supported |
| --- | --- |
| `main` (latest) | ✅ |
| older tags / `dev` work-in-progress | ❌ |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security problems.**

Report privately using GitHub's **[Security Advisories](https://github.com/NaeemHaque/livegoal/security/advisories/new)**
("Report a vulnerability"), or email the maintainer directly. Include:

- a description and impact,
- steps to reproduce (a proof-of-concept if you have one),
- affected route/endpoint or file, and any relevant logs.

We aim to acknowledge reports within a few days and will coordinate a fix and disclosure timeline with you.
Please give us reasonable time to release a fix before any public disclosure.

## Scope & hardening notes

- **Secrets stay server-side.** The `FOOTBALL_DATA_TOKEN` lives only in `.env`; it is never sent to the
  browser. The frontend talks only to this app's own `/api`. Never commit `.env` or any token.
- **The scheduler endpoint is token-guarded.** `GET /scheduler/run` is disabled unless `SCHEDULER_TOKEN` is
  set, and it 404s on a missing/incorrect token. Use a long, random value in production.
- **No third-party realtime infrastructure.** v1 is poll-only — no Pusher/Reverb/Echo/SSE/Redis — which
  keeps the externally reachable surface small (the SPA, the read-only `/api`, and the guarded scheduler ping).
- **Read-only upstream.** The app only reads from football-data.org and serves cached JSON; it accepts no
  user-supplied data that reaches the upstream API.

## What's not a vulnerability

- Delayed or missing live data, absent lineups/events/detailed stats — these are
  [free-tier limits](README.md#free-tier-caveats) of football-data.org, surfaced intentionally in the UI.
- Rate-limit (HTTP 429) responses from the upstream provider during heavy local testing.
