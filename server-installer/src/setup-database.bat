@echo off
title PlexiQ LIMS - Database Setup
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS - Database Setup
echo ========================================
echo.

REM Run PowerShell database setup
powershell -ExecutionPolicy Bypass -File "setup-database.ps1"
if %errorlevel% neq 0 (
    echo.
    echo [FAIL] Database setup failed.
    echo Check PostgreSQL is installed and running.
    pause
    exit /b 1
)

echo.
pause
