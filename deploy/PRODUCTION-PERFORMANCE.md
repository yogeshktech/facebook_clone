# Live server performance (504 / slow chat)

A **504 Gateway Timeout** on `newbook.workarya.com` almost always means PHP-FPM workers are **stuck or overloaded**. Chat was blocking on Reverb/broadcast and hitting the database on every poll.

## Required services (Supervisor)

Run these **always** on production:

```bash
php artisan reverb:start
php artisan queue:work --sleep=1 --tries=3 --max-time=3600
```

### Supervisor example

Copy `deploy/supervisor-newbook.conf.example` to `/etc/supervisor/conf.d/newbook.conf`, update paths, then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

You should see `newbook-reverb` and `newbook-queue` **RUNNING**.

## Required `.env` on live

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file

REVERB_APP_ID=newbook-live
REVERB_APP_KEY=your-key
REVERB_APP_SECRET=your-secret
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Browser (public domain via nginx /app proxy)
REVERB_CLIENT_HOST=newbook.workarya.com
REVERB_CLIENT_PORT=443
REVERB_CLIENT_SCHEME=https

# PHP queue worker → Reverb API (same server, internal)
REVERB_BROADCAST_HOST=127.0.0.1
REVERB_BROADCAST_PORT=8080
REVERB_BROADCAST_SCHEME=http
```

After changes:

```bash
php artisan config:clear
php artisan cache:clear
php artisan queue:restart
```

## Fix: `Address already in use` on port 8080

Reverb is **already running**. Do not start it twice.

```bash
sudo supervisorctl status          # newbook-reverb should be RUNNING
ss -tlnp | grep 8080               # see what holds port 8080
```

Run **only** the queue worker in a second process:

```bash
php artisan queue:work database --sleep=1 --tries=3
```

Never run `reverb:start` manually if Supervisor already runs it.

## Fix: `MessageSent` / `LiveNotification` queue jobs FAIL

Cause: queue worker was trying to reach Reverb at the **public HTTPS URL** instead of `127.0.0.1:8080`.

1. Set `REVERB_BROADCAST_HOST=127.0.0.1` (see `.env` above)
2. `php artisan config:clear`
3. Ensure Reverb is running: `ss -tlnp | grep 8080`
4. Retry failed jobs:

```bash
php artisan queue:retry all
# or clear old failures:
php artisan queue:flush
```

## Nginx

Ensure WebSocket proxy exists (see `deploy/nginx-newbook.conf`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_read_timeout 86400;
}
```

PHP block should allow long uploads but not hang forever:

```nginx
fastcgi_read_timeout 120;
```

## Verify

```bash
# Reverb listening
ss -tlnp | grep 8080

# Queue worker running
ps aux | grep "queue:work"

# Failed jobs
php artisan queue:failed
```

## What we optimized in code

- Message/typing broadcasts are **queued** (no blocking wait for Reverb)
- Read receipts throttled (not every poll)
- `last_seen` update deferred after response
- Chat polling reduced when Echo is connected (15s backup)

Without **Reverb + queue worker**, chat still saves messages but real-time delivery and typing will be delayed.
