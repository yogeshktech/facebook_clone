@echo off
cd /d "%~dp0"
echo ============================================
echo  Facebook Clone - Package Check and Install
echo ============================================
echo.

echo [1] Composer packages...
composer show laravel/sanctum 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Sanctum NOT installed. Installing...
    composer require laravel/sanctum laravel/socialite --no-interaction
) else (
    echo Sanctum OK
)
composer show laravel/socialite 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Socialite NOT installed. Installing...
    composer require laravel/socialite --no-interaction
) else (
    echo Socialite OK
)

composer show league/flysystem-aws-s3-v3 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo S3 Flysystem NOT installed. Installing...
    composer require league/flysystem-aws-s3-v3 aws/aws-sdk-php --no-interaction
) else (
    echo S3 Flysystem OK
)

echo.
echo [2] NPM packages...
if not exist node_modules (
    echo Installing npm packages...
    call npm install
) else (
    echo node_modules OK
)

if not exist public\build\manifest.json (
    echo Building Vite assets...
    call npm run build
) else (
    echo Vite build OK
)

echo.
echo [3] Database migrate...
php artisan migrate --force

echo.
echo [4] Seed dummy data...
php artisan db:seed --force

echo.
echo ============================================
echo  Done! Run: php artisan serve
echo  Login: demo@facebook-clone.test OR 9876543210
echo  Password: password
echo ============================================
pause
