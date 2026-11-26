@echo off
title PlexiQ LIMS - Development Server
cd /d "%~dp0"

echo ============================================================
echo   PlexiQ LIMS - Regulated Enterprise Laboratory System
echo ============================================================
echo.

:: Detect PHP binary
set "PHP_BIN=php"
where php >nul 2>&1
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP was not found in your PATH or at C:\xampp\php\php.exe.
        echo Please ensure PHP is installed or added to your system PATH.
        echo.
        pause
        exit /b 1
    )
)

echo [OK] Using PHP: %PHP_BIN%
echo [OK] Server URL: http://127.0.0.1:8080
echo.
echo Default Credentials:
echo   - Administrator : admin    / Admin123!
echo   - Quality Analyst: analyst  / Admin123!
echo   - QA Reviewer   : reviewer / Admin123!
echo   - QA Approver   : approver / Admin123!
echo.
echo Starting development server...
echo Press Ctrl+C in this window to stop the server.
echo.

:: Automatically open browser after 1 second
start "" cmd /c "timeout /t 2 /nobreak >nul & start http://127.0.0.1:8080"

:: Start PHP built-in server with PostgreSQL PDO extensions enabled
"%PHP_BIN%" -d extension=pdo_pgsql -d extension=pgsql -S 127.0.0.1:8080 -t public public/router.php

pause
