# Production setup: real-time chat + audio/video calls

Live server par agar `cdn.tailwindcss.com` warning dikhe, matlab **`public/build/` server par nahi hai**. Calls tab CDN fallback use karti hain — Echo + call buttons ab fallback mein bhi hain, lekin **recommended: `npm run build` deploy karo**.

## 1. Frontend build (required for best experience)

On server (or build locally and upload `public/build/`):

```bash
cd /var/www/newbook
bash deploy/build-assets.sh
# OR: npm ci && npm run build
php artisan view:clear
```

## 2. `.env` on live server

```env
APP_URL=https://newbook.workarya.com

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=newbook-live
REVERB_APP_KEY=your-random-key
REVERB_APP_SECRET=your-random-secret
# Browser (public domain via nginx /app proxy)
REVERB_CLIENT_HOST=newbook.workarya.com
REVERB_CLIENT_PORT=443
REVERB_CLIENT_SCHEME=https

# PHP queue worker → Reverb API (same server, internal)
REVERB_BROADCAST_HOST=127.0.0.1
REVERB_BROADCAST_PORT=8080
REVERB_BROADCAST_SCHEME=http

# Legacy fallbacks
REVERB_HOST=newbook.workarya.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_CLIENT_HOST}"
VITE_REVERB_PORT="${REVERB_CLIENT_PORT}"
VITE_REVERB_SCHEME="${REVERB_CLIENT_SCHEME}"
```

**Important:** Build assets **on the server** (or with live `.env` values), not only on your PC:

```bash
cd /var/www/newbook
npm ci && npm run build
php artisan config:clear && php artisan view:clear
```

If you build locally and upload `public/build/`, the JS may still use wrong WebSocket port — the app now prefers `window.reverbConfig` from the server, but Reverb process + nginx must be correct.

Then:

```bash
php artisan config:clear
php artisan cache:clear
```

## 3. Reverb process (must stay running)

```bash
php artisan reverb:start
```

Production mein **Supervisor** use karo, example:

```ini
[program:newbook-reverb]
command=php /var/www/newbook/artisan reverb:start
directory=/var/www/newbook
autostart=true
autorestart=true
user=www-data
```

Queue worker (broadcast jobs):

```ini
[program:newbook-queue]
command=php /var/www/newbook/artisan queue:work database --sleep=3 --tries=3
directory=/var/www/newbook
autostart=true
autorestart=true
user=www-data
```

## 4. Nginx WebSocket proxy

Copy updated config (includes `/app` → Reverb):

```bash
sudo cp deploy/nginx-newbook.conf /etc/nginx/sites-available/newbook
sudo nginx -t
sudo systemctl reload nginx
```

## 5. Verify

1. Browser console: `window.Echo` should exist (logged-in chat page)
2. No red WebSocket errors to `wss://newbook.workarya.com/app/...`
3. Chat call buttons → outgoing call UI opens
4. Second user → incoming call ring

## Common issues

| Symptom | Fix |
|--------|-----|
| `cdn.tailwindcss.com` warning | Run `npm run build`, deploy `public/build/` |
| `window.Echo` undefined | Check `REVERB_APP_KEY`, `BROADCAST_CONNECTION=reverb`, echo-cdn loads |
| WebSocket failed | Nginx `/app` proxy + `php artisan reverb:start` |
| Call button does nothing | Hard refresh; ensure build or updated fallback deployed |
| Works locally, not live | 1) `sudo supervisorctl status` → `newbook-reverb` RUNNING 2) nginx `/app` → `127.0.0.1:8080` 3) `.env` `REVERB_BROADCAST_HOST=127.0.0.1` 4) `curl https://newbook.workarya.com/chat/call/health` → `{"ok":true}` |
| Could not reach call server | Reverb not running OR nginx WebSocket proxy missing OR `php artisan config:clear` not run after `.env` change |
