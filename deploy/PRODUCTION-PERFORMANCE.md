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
```

Use `CACHE_STORE=file` and `SESSION_DRIVER=file` on a single server — **not** `database` for both (adds DB load on every request).

Reverb keys must match:

```env
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=newbook.workarya.com
REVERB_PORT=443
REVERB_SCHEME=https
```

After changes:

```bash
php artisan config:clear
php artisan cache:clear
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
