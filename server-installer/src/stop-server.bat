@echo off
title PlexiQ LIMS Server (Stopping...)
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS Server - Stopping
echo ========================================
echo.

tasklist /FI "WINDOWTITLE eq PlexiQ LIMS Server*" 2>nul | findstr /i "php.exe" >nul
if %errorlevel% equ 0 (
    taskkill /F /FI "WINDOWTITLE eq PlexiQ LIMS Server*" /T
    echo [OK] PlexiQ LIMS Server stopped.
) else (
    echo [INFO] PlexiQ LIMS Server is not running.
)

echo.
pause
