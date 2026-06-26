# Facebook Clone - Setup Script (PowerShell)
Set-Location $PSScriptRoot

Write-Host "=== Facebook Clone Setup ===" -ForegroundColor Cyan

Write-Host "`n[1/8] Installing Composer packages..." -ForegroundColor Yellow
composer require laravel/sanctum laravel/socialite --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[2/8] Setting up .env..." -ForegroundColor Yellow
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
$envContent = Get-Content .env -Raw
if ($envContent -notmatch 'APP_KEY=base64:') {
    php artisan key:generate --force
}

Write-Host "`n[3/8] Creating SQLite database..." -ForegroundColor Yellow
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
}

Write-Host "`n[4/8] Linking storage..." -ForegroundColor Yellow
php artisan storage:link

Write-Host "`n[5/8] Running migrations..." -ForegroundColor Yellow
php artisan migrate --force

Write-Host "`n[6/8] Seeding database..." -ForegroundColor Yellow
php artisan db:seed --force

Write-Host "`n[7/8] Installing npm packages..." -ForegroundColor Yellow
npm install

Write-Host "`n[8/8] Building assets..." -ForegroundColor Yellow
npm run build

Write-Host "`n=== Setup Complete! ===" -ForegroundColor Green
Write-Host "Run: php artisan serve" -ForegroundColor Cyan
Write-Host "Login: demo@facebook-clone.test / password" -ForegroundColor Cyan
