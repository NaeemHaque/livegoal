# Deployment Guide — shipping an update to the live server

A friendly, **step-by-step** runbook for putting new code onto the live site
(<https://livegoal.win>). It assumes no prior ops experience — every command
says what it does and what you should see.

> This is the **hands-on runbook** for updating the running server.
> [`DEPLOY.md`](DEPLOY.md) is the reference for setting up a brand-new host from
> scratch (nginx, PHP-FPM, first-time `.env`). If the server already exists —
> which it does — you want **this** file.

---

## The mental model (read this once)

LiveGoal is **one server doing everything**: it serves the website, the `/api`,
and runs the score poller. There is no separate frontend host, no database
server, no message broker. So a deploy is short:

> **Deploy = put a tagged version of the code on the server → rebuild → restart the background worker → check it's up.**

About **3–5 minutes**, ~3 copy-paste blocks. There's no fancy zero-downtime
tooling; the site blips for a second while assets rebuild, and that's fine for a
free-tier project.

Two ideas that make the rest of this guide click:

- **We deploy a _tag_, not a branch.** A tag like `v1.2.0` is a frozen, named
  snapshot of the code. The server checks out that exact tag. (The server's own
  `main` branch has diverged history — never `git pull` on the server. Always
  check out a tag.)
- **The app files are owned by the `www-data` user**, the same user the web
  server runs as. So all the `git`/`build`/`artisan` commands must run **as
  `www-data`**, or you'll create files the web server can't read. We do that with
  `sudo -u www-data`.

---

## Before you start — checklist

- [ ] Your change is **merged into `dev`** on GitHub (via a PR, as usual).
- [ ] Your local `dev` is up to date: `git checkout dev && git pull`.
- [ ] You can SSH to the server (you have the key `~/.ssh/id_ed25519`).
- [ ] You know the server's **public IP** (the address `livegoal.win` points to).
      In the commands below it's written as `<SERVER_IP>` — replace it.
- [ ] The gate is green locally if you changed PHP: `composer ci:check`.

> **Tip:** wherever a command below has `<SERVER_IP>` or `vX.Y.Z`, swap in the
> real value before pressing enter.

---

## Step 1 — Cut a release (a version tag) on your Mac

Versions are tracked purely by **git tags** (there's no version file to edit).
We use **semver** — `vMAJOR.MINOR.PATCH`:

| Bump | When | Example |
| --- | --- | --- |
| **PATCH** (`v1.1.1` → `v1.1.2`) | bug fixes / tiny tweaks only | a label typo fix |
| **MINOR** (`v1.1.1` → `v1.2.0`) | new user-facing feature, backwards-compatible | the match-start push |
| **MAJOR** (`v1.1.1` → `v2.0.0`) | a big rework / breaking change | rare |

Find the last version, then pick the next one:

```bash
git tag --sort=-creatordate | head -3      # newest tags first; latest is v1.1.1
```

Create and push the new tag from an up-to-date `dev`, then publish a GitHub
Release (so there's a changelog people can read):

```bash
git checkout dev && git pull

git tag -a v1.2.0 -m "v1.2.0 — match-start push + World Cup landing rail"
git push origin v1.2.0

gh release create v1.2.0 \
  --target dev \
  --title "LiveGoal v1.2.0" \
  --notes "Match-start push notifications, World Cup landing rail, Group D labels."
```

**What you should see:** the tag appears at
`https://github.com/NaeemHaque/livegoal/releases`.

> Doing a quick **test deploy** and don't want to bump the version? You can skip
> this step and check out `dev` directly on the server in Step 2
> (`git checkout dev && git pull --ff-only`). For a real release, always tag.

---

## Step 2 — Deploy the code on the server

SSH in (you log in as the `ubuntu` user, who is allowed to `sudo`):

```bash
ssh -i ~/.ssh/id_ed25519 ubuntu@<SERVER_IP>
```

Now run the deploy **as `www-data`**. Copy this **whole block** and paste it in
one go — it's a single command that ends at the line `DEPLOY`:

```bash
sudo -u www-data bash -s <<'DEPLOY'
export COMPOSER_HOME=/tmp/.composer       # writable cache dirs for www-data
export npm_config_cache=/tmp/.npm
cd /var/www/livegoal
git fetch origin --tags                   # pull down the new tag
git checkout v1.2.0                        # <-- the version you tagged in Step 1
composer install --no-dev -o              # PHP deps (production only, optimised)
npm ci --include=dev                      # JS deps (dev deps needed to build)
npm run build                             # compile the Vue app into public/build
php artisan migrate --force               # apply any new DB migrations
php artisan optimize:clear                # drop old caches before rebuilding them
php artisan config:cache                  # cache config (also sends VAPID key to the app)
php artisan route:cache
php artisan view:cache
echo "DEPLOY-DONE"
DEPLOY
```

**What you should see:** a stream of output ending in **`DEPLOY-DONE`**. That
last marker is your success signal — if you see it with no red `error`/`fatal`
lines above, the code is live.

> **Why a heredoc?** Pasting one block avoids long `&&` chains that wrap and
> break in the terminal, and runs every command as the correct user. Run it
> exactly as-is.

### What each command does (for the curious)

- `git fetch --tags` + `git checkout v1.2.0` — switch the server to the exact
  released snapshot.
- `composer install --no-dev -o` — install PHP libraries (skip dev tools, build
  an optimised autoloader).
- `npm ci --include=dev` + `npm run build` — install JS libraries and compile the
  Vue SPA into `public/build/`. The browser only ever loads this compiled output.
- `migrate --force` — run new database migrations. `--force` just means "yes,
  run in production" (no scary prompt). Migrations here are **additive** (new
  tables/columns), so this is safe.
- `optimize:clear` then `config:cache` / `route:cache` / `view:cache` — clear
  stale caches, then re-cache for speed. Re-caching config is what pushes the
  VAPID public key into the page for web-push.

---

## Step 3 — Restart the background worker

Push notifications (goal / full-time / **match start**) are **queued** jobs
processed by a small always-on worker (`livegoal-queue`). The worker keeps the
**old code in memory**, so after every deploy you must restart it — otherwise new
pushes (or push code changes) won't take effect.

Still on the server (this one needs `sudo`, so run it as yourself, not www-data):

```bash
sudo systemctl restart livegoal-queue
sudo systemctl is-active livegoal-queue       # should print: active
```

**What you should see:** `active`. If it says `failed`, jump to Troubleshooting.

> You do **not** need to restart nginx or PHP-FPM for a normal code deploy.

---

## Step 4 — Verify it worked

From your Mac (or anywhere):

```bash
# 1. The site responds (expect: 200)
curl -s -o /dev/null -w "%{http_code}\n" https://livegoal.win/

# 2. The cached API serves (expect: a JSON blob, not an error)
curl -s https://livegoal.win/api/live | head -c 200
```

Then open <https://livegoal.win> in a browser and check:

- The page loads and looks right.
- The top bar's **"updated Xs ago"** timestamp ticks/advances — that proves the
  live poller is running.
- **Hard-refresh** (Cmd-Shift-R). The page should pull fresh
  `/build/assets/…` files (new filename hashes) — if you still see the old UI,
  see "stale assets" in Troubleshooting.

To smoke-test push end-to-end, with a **hidden** browser tab subscribed:

```bash
# on the server, as www-data:
sudo -u www-data php /var/www/livegoal/artisan app:push-test
```

(Visible tabs suppress the OS notification by design, so keep the tab hidden.)

✅ **Done.** The new version is live.

---

## If something goes wrong — rollback

Rolling back is just deploying the **previous** tag. Pick the last good version
(e.g. `v1.1.1`) and re-run Step 2 with it, then restart the worker:

```bash
sudo -u www-data bash -s <<'ROLLBACK'
export COMPOSER_HOME=/tmp/.composer
export npm_config_cache=/tmp/.npm
cd /var/www/livegoal
git fetch origin --tags
git checkout v1.1.1            # <-- the previous good version
composer install --no-dev -o
npm ci --include=dev
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "ROLLBACK-DONE"
ROLLBACK

sudo systemctl restart livegoal-queue
```

> Migrations are additive, so you normally **don't** undo the database on a
> rollback — old code simply ignores new columns. Only touch the DB if a specific
> migration is the problem (and then take a copy of `database/database.sqlite`
> first).

---

## Troubleshooting

| Symptom | Likely cause & fix |
| --- | --- |
| You **don't** see `DEPLOY-DONE` | A command failed above it. Read the last red line. Common: `composer`/`npm` network hiccup — just re-run the block. |
| `Permission denied` during git/build | You're not running as `www-data`. Use the `sudo -u www-data bash -s <<'DEPLOY'` block, not a bare `cd`. |
| `git checkout` fails with an auth error | The deploy must keep `www-data`'s home (its GitHub SSH key lives there). Don't add `-H`/`-i` to the `sudo`; run the block exactly as written. |
| Browser still shows the **old** UI | Stale caches/assets. Re-run `php artisan optimize:clear` then the three `*:cache` commands, confirm `npm run build` succeeded, and hard-refresh. Check the page's `/build/assets/*` filenames changed. |
| **Push notifications** stop arriving | The worker isn't running. `sudo systemctl status livegoal-queue` → if not `active`, `sudo systemctl restart livegoal-queue` and check `journalctl -u livegoal-queue -n 50`. |
| Site returns **500** after deploy | Check `tail -n 50 /var/www/livegoal/storage/logs/laravel.log`. Usually a missed `migrate --force` or a config cache built before `.env` was right — re-run migrate + `config:cache`. |
| **Scores not updating** | That's the poller, driven by cron / the scheduler — separate from a deploy. See [`LIVE_POLLING.md`](LIVE_POLLING.md) and `DEPLOY.md` §6. |

---

## Quick reference (the whole thing)

```bash
# 1) On your Mac — tag the release
git checkout dev && git pull
git tag -a v1.2.0 -m "v1.2.0 — <summary>"
git push origin v1.2.0
gh release create v1.2.0 --target dev --title "LiveGoal v1.2.0" --notes "<notes>"

# 2) On the server — deploy as www-data
ssh -i ~/.ssh/id_ed25519 ubuntu@<SERVER_IP>
sudo -u www-data bash -s <<'DEPLOY'
export COMPOSER_HOME=/tmp/.composer
export npm_config_cache=/tmp/.npm
cd /var/www/livegoal
git fetch origin --tags
git checkout v1.2.0
composer install --no-dev -o
npm ci --include=dev
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "DEPLOY-DONE"
DEPLOY

# 3) Restart the worker + check
sudo systemctl restart livegoal-queue
sudo systemctl is-active livegoal-queue

# 4) Verify (from anywhere)
curl -s -o /dev/null -w "%{http_code}\n" https://livegoal.win/
```

---

### Related docs

- [`DEPLOY.md`](DEPLOY.md) — first-time host setup (nginx, PHP-FPM, `.env`).
- [`LIVE_POLLING.md`](LIVE_POLLING.md) — how "realtime" scores work (the poller).
- [`PUSH_NOTIFICATIONS.md`](PUSH_NOTIFICATIONS.md) — the web-push pipeline and VAPID.
