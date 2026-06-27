@echo off
echo ===================================================
echo Running Database Seeders for Facebook Ads Setup
echo ===================================================
echo.
php artisan db:seed --force
echo.
echo ===================================================
echo Database seeding completed successfully!
echo ===================================================
pause
