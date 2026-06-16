# Deploying LiveGoal

LiveGoal is **one Laravel app**: it serves the cached JSON `/api`, the compiled Vue SPA, and runs a single
scheduled poller against football-data.org. There is no separate frontend host, no websocket server, and no
external queue/broker to stand up — a single PHP host with one cron line runs the whole product.

This guide covers a generic single-host deploy (nginx/Apache + PHP-FPM). For a zero-config path, see
[Laravel Cloud](https://cloud.laravel.com) at the end.

---

## 1. Requirements

- **PHP 8.4+** with the usual Laravel extensions (`mbstring`, `openssl`, `pdo`, `curl`, `dom`, `fileinfo`).
- **Composer 2**.
- **Node 20+** — only to build assets (build on the server, or build in CI and ship `public/build`).
- A web server (**nginx** or **Apache**) with the docroot at `public/`.
- A free **football-data.org** token — register at <https://www.football-data.org/client/register>.

## 2. Get the code & dependencies

```bash
git clone git@github.com:NaeemHaque/livegoal.git
cd livegoal

composer install --no-dev --optimize-autoloader
npm ci
npm run build            # compiles the SPA into public/build
```

> Building on the server needs Node; if the host has no Node, run `npm ci && npm run build` in CI and deploy
> the `public/build/` directory alongside the code.

## 3. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Set at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://livegoal.win          # your real https URL

FOOTBALL_DATA_TOKEN=xxxxxxxx          # server-side only — never shipped to the browser

CACHE_STORE=database                 # poller + /api cache live here (or: file)
DB_CONNECTION=sqlite                 # default; database/database.sqlite

# Only if the host has NO system cron (see §6):
SCHEDULER_TOKEN=                     # a long random string enables /scheduler/run
```

Keep `FOOTBALL_DATA_TOKEN` and `SCHEDULER_TOKEN` **out of git** — they live only in the server's `.env`.

## 4. Migrate & cache config

```bash
php artisan migrate --force          # creates the cache table when CACHE_STORE=database

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ensure `storage/` and `bootstrap/cache/` are writable by the web user.

## 5. Web server

Point the docroot at **`public/`** and use the standard Laravel front-controller rewrite. The SPA needs no
special config: deep links like `/team/57` are handled by Laravel's catch-all route (`routes/web.php`'s
`Route::fallback`), which returns the SPA shell so the Vue router can take over. There is **no CORS** to
configure — API and SPA share one origin.

nginx example:

```nginx
server {
    listen 443 ssl;
    server_name livegoal.win;
    root /var/www/livegoal/public;

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SSL via Let's Encrypt / your provider
}
```

## 6. Keep scores live (the poller)

"Realtime" is one scheduled command (`app:poll-live-scores`, every minute) — see
[`LIVE_POLLING.md`](LIVE_POLLING.md). Pick **one** of:

- **System cron (preferred):** one line drives Laravel's whole schedule.
  ```bash
  * * * * * cd /var/www/livegoal && php artisan schedule:run >> /dev/null 2>&1
  ```
- **No system cron:** set `SCHEDULER_TOKEN` and point a free pinger (e.g. <https://cron-job.org>) at
  `GET https://livegoal.win/scheduler/run?token=<SCHEDULER_TOKEN>` every minute. The route is token-guarded
  (404s without the exact token, disabled when the token is empty) and **rate-limited to 20 requests/minute**
  per IP, which is far above the once-a-minute legitimate ping.

## 7. Verify

```bash
curl -s https://livegoal.win/api/competitions | head -c 200    # cached JSON
curl -s https://livegoal.win/api/live | head -c 200            # poller output (empty list off-season is fine)
```

Open the site: the top bar shows an "updated Xs ago" timestamp that advances as the poller runs. If the
device goes offline, an offline banner appears under the nav.

## 8. Updating a live deploy

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart            # let the push worker pick up the new code (see §9)
```

## 9. Push notifications (queue worker + VAPID)

Goal / full-time **web-push alerts** (see [`PUSH_NOTIFICATIONS.md`](PUSH_NOTIFICATIONS.md)) are sent as
**queued** notifications, so production needs a **running queue worker** — without one the jobs pile up in the
`jobs` table and nothing is ever delivered. With `QUEUE_CONNECTION=database` (the default) no broker is needed.

**VAPID keys** (one-time). Generate a keypair into `.env`, set the subject, then rebuild the config cache so
the public key reaches the SPA's `<meta name="vapid-public-key">`:

```bash
php artisan webpush:vapid          # writes VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY to .env
# set VAPID_SUBJECT=mailto:you@yourdomain in .env (keep the private key quoted — it can contain newlines)
php artisan config:cache
```

> Keys are stable: **do not regenerate** once subscribers exist, or every existing subscription breaks.

**Queue worker** (systemd). One always-on worker drains the queue within the poll cycle:

```ini
# /etc/systemd/system/livegoal-queue.service
[Unit]
Description=LiveGoal queue worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/livegoal
ExecStart=/usr/bin/php artisan queue:work --sleep=1 --tries=3 --max-time=3600
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now livegoal-queue     # start + run on boot
sudo systemctl status livegoal-queue           # confirm "active (running)"
```

`--max-time=3600` recycles the worker hourly to cap memory; `php artisan queue:restart` (in §8) tells it to
reload code on each deploy. Expired endpoints are pruned automatically on send; orphaned subscribers are swept
by the daily `model:prune` schedule. Smoke-test end to end with `php artisan app:push-test` (sends a demo goal
to every subscriber — keep the browser tab **hidden**, since visible tabs suppress the OS notification).

## Laravel Cloud (zero-config alternative)

[Laravel Cloud](https://cloud.laravel.com) deploys this repo without provisioning a server: set the env vars
above, enable the **scheduler** (it runs `schedule:run` for you, so you don't need cron or `SCHEDULER_TOKEN`),
and it builds assets and serves `public/` automatically.
