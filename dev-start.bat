@echo off
title NEWBOOK Local Dev
cd /d "%~dp0"

echo ============================================
echo  NEWBOOK - Starting local dev services
echo ============================================
echo.
echo  1) Laravel  - http://localhost:8000
echo  2) Reverb   - ws://localhost:8080  (REQUIRED for calls + chat)
echo  3) Queue    - background jobs
echo.
echo  Keep all 3 windows open while testing.
echo ============================================
echo.

start "NEWBOOK - Laravel" cmd /k "php artisan serve"
timeout /t 2 /nobreak >nul
start "NEWBOOK - Reverb" cmd /k "php artisan reverb:start"
timeout /t 1 /nobreak >nul
start "NEWBOOK - Queue" cmd /k "php artisan queue:work database --sleep=1 --tries=3"

echo All services started in separate windows.
pause
