# Fix 413 / PostTooLargeException (reels, stories, large uploads)

Your error:

```
POST Content-Length of 21122192 bytes exceeds the limit of 8388608 bytes
```

means **PHP `post_max_size` is only 8MB** on the live server. A ~21MB reel will fail until limits are increased.

## One-command fix (SSH on live server)

```bash
cd /var/www/newbook   # your project path
sudo bash deploy/fix-upload-limits.sh
```

Or manually copy the ini file:

```bash
sudo cp deploy/php-upload-limits.ini /etc/php/8.2/fpm/conf.d/99-newbook-uploads.ini
sudo systemctl restart php8.2-fpm
```

Then add to nginx site config (`client_max_body_size 128M;`) and `sudo systemctl reload nginx`.

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
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 600
memory_limit = 512M
```

```bash
sudo systemctl restart php8.2-fpm
```

Verify:

```bash
php -i | grep post_max_size
```

Should show `110M`, not `8M`.

### 2. Nginx limits

```bash
sudo nano /etc/nginx/sites-available/newbook
```

Inside the `server { ... }` block for `newbook.workarya.com`, add or update:

```nginx
client_max_body_size 128M;
```

In the `location ~ \.php$` block, add:

```nginx
fastcgi_read_timeout 300;
fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=110M \n max_execution_time=600";
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
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 600
memory_limit = 512M
```

```bash
sudo systemctl restart php8.2-fpm
```

## Full example config

See `deploy/nginx-newbook.conf` in this repo.

## App limits

- Reels, stories, posts, chat video: up to **100MB**
- Images auto-compressed in browser before upload
- Server must allow **128MB nginx** + **110MB PHP post_max_size**
