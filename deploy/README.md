# Fix 413 / PostTooLargeException (reels, stories, large uploads)

Your error `POST Content-Length exceeds the limit of 8388608 bytes` means **PHP post_max_size is only 8MB**.
A 26MB reel video will fail until server limits are increased.

## Quick fix on live server (SSH) — REQUIRED for reels

### 1. PHP limits (most important)

```bash
sudo cp deploy/php-upload-limits.ini /etc/php/8.2/fpm/conf.d/99-newbook-uploads.ini
sudo systemctl restart php8.2-fpm
```

Or edit manually:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
upload_max_filesize = 64M
post_max_size = 70M
max_execution_time = 300
```

```bash
sudo systemctl restart php8.2-fpm
```

Verify:

```bash
php -i | grep post_max_size
```

Should show `70M`, not `8M`.

### 2. Nginx limits

```bash
sudo nano /etc/nginx/sites-available/newbook
```

Inside the `server { ... }` block for `newbook.workarya.com`, add or update:

```nginx
client_max_body_size 64M;
```

In the `location ~ \.php$` block, add:

```nginx
fastcgi_read_timeout 300;
fastcgi_param PHP_VALUE "upload_max_filesize=64M \n post_max_size=70M \n max_execution_time=300";
```

Then:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Also update PHP-FPM pool if needed:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Set:

```ini
upload_max_filesize = 64M
post_max_size = 70M
max_execution_time = 300
```

```bash
sudo systemctl restart php8.2-fpm
```

## Full example config

See `deploy/nginx-newbook.conf` in this repo.

## App limits

- Reels: up to **50MB** video
- Stories & posts: up to **50MB** video, images auto-compressed in browser
- Server must allow **at least 64MB** (nginx + PHP) or uploads will fail
