@echo off
echo ===================================================
echo Running Database Migrations for Facebook Ads Setup
echo ===================================================
echo.
php artisan migrate --force
echo.
echo ===================================================
echo Database migrations completed successfully!
echo ===================================================
pause
