# Push notifications — goal & full-time alerts

Browser push notifications for followed teams and competitions: a **GOAL** or **FULL-TIME**
in a relevant match notifies the user when they are *not* actively on the site. The in-app
goal toast already covers visible tabs (`useLiveMatches` pauses polling on hidden tabs —
push exists precisely for the hidden/closed case).

Free end to end: the **Web Push API** with self-generated **VAPID** keys, delivered through
the browser vendors' own push services. No third-party service, no accounts, no fees.

- Laravel side: the built-in notifications framework + the
  [`laravel-notification-channels/webpush`](https://github.com/laravel-notification-channels/webpush)
  channel (MIT).
- Browser side: a push-only service worker at `/sw.js` (no offline caching).
- Users stay anonymous: a push subscription carries its own snapshot of the visitor's
  follow list (localStorage `pp_favorites`), synced on every change.

## Flows

### Subscribe

1. User follows a team/competition (match-card star, team/competition follow button) or
   flips **Match alerts** in Settings.
2. On the first follow with `Notification.permission === 'default'`, a small dismissible
   in-app banner offers to enable alerts. The native permission prompt fires only from
   that button click (a real gesture) — never on page load.
3. On grant: register `/sw.js`, `pushManager.subscribe({ userVisibleOnly: true,
   applicationServerKey })` — the VAPID public key is exposed via
   `<meta name="vapid-public-key">` in the app shell.
4. `POST /api/push/subscriptions` with
   `{ endpoint, keys: {p256dh, auth}, contentEncoding, follows: [...pp_favorites] }`.
   The server upserts one anonymous `push_subscribers` row, its `push_subscriptions`
   row (package table), and replaces its `push_follows` rows. Responds 204.
5. A debounced watcher on the favorites store re-POSTs the full payload on every follow
   change; app boot re-syncs once to repair localStorage/server drift.

### Notify

1. The live poller (`app:poll-live-scores`, every 30s) appends GOAL / FT timeline events
   **exactly once per real event** — every upstream flap/dedupe guard lives there (see
   `docs/LIVE_POLLING.md`).
2. At those two append points the poller calls `MatchAlerts::goalScored()` /
   `MatchAlerts::fullTime()`, which resolve the audience — subscribers following either
   team or the competition — and queue `GoalScored` / `MatchFullTime` notifications.
3. The existing database-queue worker performs the web-push HTTP sends. The poller itself
   only enqueues.
4. Sends use a short TTL (goals 600s, FT 1800s) and a per-match `tag`, so stale or
   superseded notifications collapse instead of stacking.

### Display / suppress

- SW `push`: if any window client is **visible**, do nothing — the in-app toast owns that
  case. Otherwise `showNotification` straight from the payload.
- SW `notificationclick`: focus an open tab and navigate to `/match/{id}`, else open one.
- SW `pushsubscriptionchange`: re-subscribe and re-POST with `oldEndpoint` (and no
  `follows` key) — the server re-keys the same subscriber, keeping its follows.

### Unsubscribe / prune

- Settings toggle off → `subscription.unsubscribe()` + `DELETE /api/push/subscriptions`
  → subscriber, subscription and follow rows deleted.
- Expired endpoints (404/410 on send) → the package's report handler deletes the
  subscription row automatically; orphaned subscriber rows are swept by `Prunable`
  (one week after losing their subscription) via the daily `model:prune` schedule.

## Data model

| Table | Columns | Notes |
| --- | --- | --- |
| `push_subscriptions` | morph subscribable, `endpoint` (unique), `public_key`, `auth_token`, `content_encoding` | Package-published migration, committed as-is |
| `push_subscribers` | `id`, timestamps | One row per browser; `updated_at` touched on sync, drives pruning |
| `push_follows` | `push_subscriber_id` FK (cascade), `type` (`team`\|`competition`), `followed_id` (string) | UNIQUE(subscriber, type, id) + INDEX(type, id); replaced wholesale on sync |

Follow ids are strings end to end (the normalizer and the favorites store both cast).
Audience resolution is one indexed `whereHas` per event:
team ∈ {home, away} OR competition = match's competition, chunked by 500.

## Payloads

| | Title | Body | Options |
| --- | --- | --- | --- |
| Goal | `GOAL! Mexico 2–0 South Africa` | `73' — World Cup` | `tag: match-{id}`, `renotify`, TTL 600, urgency high |
| Full-time | `FT: Mexico 2–0 South Africa` | `World Cup` | same tag (replaces a lingering goal), TTL 1800 |

Both carry `data.url = /match/{id}` for the click handler and the team crest as icon with
`/icons/icon-192.png` as fallback. Total payload ~400 B (limit is ~4 KB).

## Ops runbook

One-time setup (already scripted nowhere — manual):

```bash
composer install            # picks up the webpush package from the lockfile
php artisan migrate --force # the three push tables
php artisan webpush:vapid   # writes VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY into .env
# add VAPID_SUBJECT=mailto:hello@livegoal.win to .env (required by Apple)
php artisan config:cache
systemctl restart livegoal-queue   # workers must load the new notification classes
```

- **Gotcha:** `webpush:vapid` writes `.env` without a trailing newline — make sure
  `VAPID_SUBJECT` lands on its own line, then sanity-check
  `strlen(config('webpush.vapid.private_key')) === 43` in tinker. A glued line
  corrupts the private key and every send fails with "Invalid data provided".
- `php artisan app:push-test` sends a test notification to every subscriber (or
  `--endpoint=` for one) — the end-to-end smoke test without waiting for a goal.
- nginx must serve `.webmanifest` as `application/manifest+json` (iOS install needs the
  manifest; see below).
- HTTPS is required by the Push API (already in place; `localhost` is exempt for dev).

## Manual test checklist (service workers aren't unit-testable server-side)

1. Settings → enable **Match alerts** → accept the prompt → a `push_subscriptions` row
   exists and `push_follows` mirrors `pp_favorites`.
2. `php artisan app:push-test` → notification arrives with the tab closed.
3. `php artisan app:demo-live` + `php artisan app:poll-live-scores`, tab hidden → goal
   notification with the right scoreline; tab visible → suppressed (toast shows instead).
4. Click the notification → focuses/opens `/match/{id}`.
5. Toggle off → all three row types gone; pushing again does nothing.
6. iPhone: add to Home Screen first (iOS 16.4+ requirement), then repeat 2.

## Platform caveats

- **iOS/iPadOS**: web push only works for sites installed to the Home Screen (16.4+).
  The Settings page shows an install hint on iOS Safari when not standalone.
- **Permission denied**: follows keep working locally; no subscription is created; the
  Settings row shows a "blocked in browser settings" state and never re-prompts.
- **localStorage cleared while a subscription lives**: its server-side follow snapshot
  keeps notifying until the next visit re-syncs, the user unsubscribes, or the endpoint
  expires. Accepted trade-off of accountless design.
- **Multiple devices**: one subscriber row per endpoint, each with its own follow
  snapshot — by design.
