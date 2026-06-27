@echo off
title Advanced LIMS - Dev Server
cd /d "%~dp0"
echo ========================================
echo   Advanced LIMS - Development Server
echo ========================================
echo.
echo Starting PHP server at http://localhost:8080
echo.
echo Open browser to: http://localhost:8080
echo Login: admin / admin@123
echo.
echo Press Ctrl+C to stop the server.
echo.
php -S localhost:8080 -t public
pause
