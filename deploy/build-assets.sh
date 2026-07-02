#!/usr/bin/env bash
# Build frontend assets for production (fixes CDN fallback + enables Vite bundle).
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v npm >/dev/null 2>&1; then
    echo "npm is required. Install Node.js 20+ first."
    exit 1
fi

npm ci
npm run build

echo ""
echo "Done. public/build/ is ready — deploy it to the server if you build locally."
echo "On server after deploy, run: php artisan config:clear && php artisan view:clear"
