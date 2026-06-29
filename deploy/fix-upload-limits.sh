#!/bin/bash
# Fix reel/story upload: POST Content-Length exceeds 8388608 bytes (8MB limit)
# Run on live server: bash deploy/fix-upload-limits.sh

set -e

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.2")
INI_DIR="/etc/php/${PHP_VER}/fpm/conf.d"
INI_FILE="${INI_DIR}/99-newbook-uploads.ini"

echo "=== NEWBOOK upload limit fix ==="
echo "PHP version: ${PHP_VER}"
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "Run with sudo: sudo bash deploy/fix-upload-limits.sh"
    exit 1
fi

mkdir -p "$INI_DIR"

cat > "$INI_FILE" << 'EOF'
; NEWBOOK — reels/stories video uploads (up to 100MB)
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 600
memory_limit = 512M
EOF

echo "Wrote $INI_FILE"

# Also patch main php.ini if post_max_size still 8M
PHP_INI="/etc/php/${PHP_VER}/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/^upload_max_filesize.*/upload_max_filesize = 100M/' "$PHP_INI" 2>/dev/null || true
    sed -i 's/^post_max_size.*/post_max_size = 110M/' "$PHP_INI" 2>/dev/null || true
    echo "Updated $PHP_INI"
fi

systemctl restart "php${PHP_VER}-fpm"
echo "Restarted php${PHP_VER}-fpm"

# Nginx client_max_body_size
NGINX_SITE="/etc/nginx/sites-available/newbook"
if [ -f "$NGINX_SITE" ]; then
    if ! grep -q 'client_max_body_size' "$NGINX_SITE"; then
        sed -i '/server_name.*newbook/a\    client_max_body_size 128M;' "$NGINX_SITE"
        echo "Added client_max_body_size to nginx site"
    else
        sed -i 's/client_max_body_size.*/client_max_body_size 128M;/' "$NGINX_SITE"
        echo "Updated client_max_body_size in nginx"
    fi
    nginx -t && systemctl reload nginx
    echo "Nginx reloaded"
else
    echo "WARN: $NGINX_SITE not found — add manually: client_max_body_size 128M;"
fi

echo ""
echo "=== Verify ==="
php -i 2>/dev/null | grep -E 'post_max_size|upload_max_filesize' || php -r 'echo "post_max_size=".ini_get("post_max_size")."\n";'
echo ""
echo "Done. Reel upload should work up to 100MB."
