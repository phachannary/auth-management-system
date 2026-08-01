@echo off
if "%1"=="local" (
    copy /Y .env.local .env
    echo Switched to LOCAL environment (http://127.0.0.1:8000)
    php artisan config:clear
) else if "%1"=="production" (
    copy /Y .env.production .env
    echo Switched to PRODUCTION environment (https://authmanagement.com)
    php artisan config:clear
) else (
    echo Usage: switch-env.bat [local^|production]
    echo.
    echo   switch-env.bat local        - Switch to local development
    echo   switch-env.bat production   - Switch to production (for upload to Namecheap)
)
