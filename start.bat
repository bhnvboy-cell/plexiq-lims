@echo off
title PlexiQ LIMS - Dev Server
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS - Development Server
echo ========================================
echo.
echo Starting PHP server at http://localhost:8080
echo Login: admin / admin@123
echo Press Ctrl+C to stop.
echo.
php -S localhost:8080 -t public
pause
