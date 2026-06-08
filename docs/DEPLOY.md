# Deploying SocPlay

SocPlay is **one Laravel app**: it serves the cached JSON `/api`, the compiled Vue SPA, and runs a single
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
git clone git@github.com:NaeemHaque/socplay.git
cd socplay

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
APP_URL=https://socplay.win          # your real https URL

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
    server_name socplay.win;
    root /var/www/socplay/public;

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
  * * * * * cd /var/www/socplay && php artisan schedule:run >> /dev/null 2>&1
  ```
- **No system cron:** set `SCHEDULER_TOKEN` and point a free pinger (e.g. <https://cron-job.org>) at
  `GET https://socplay.win/scheduler/run?token=<SCHEDULER_TOKEN>` every minute. The route is token-guarded
  (404s without the exact token, disabled when the token is empty) and **rate-limited to 20 requests/minute**
  per IP, which is far above the once-a-minute legitimate ping.

## 7. Verify

```bash
curl -s https://socplay.win/api/competitions | head -c 200    # cached JSON
curl -s https://socplay.win/api/live | head -c 200            # poller output (empty list off-season is fine)
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
```

## Laravel Cloud (zero-config alternative)

[Laravel Cloud](https://cloud.laravel.com) deploys this repo without provisioning a server: set the env vars
above, enable the **scheduler** (it runs `schedule:run` for you, so you don't need cron or `SCHEDULER_TOKEN`),
and it builds assets and serves `public/` automatically.
