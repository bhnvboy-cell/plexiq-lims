@echo off
title PlexiQ LIMS Server (Restarting...)
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS Server - Restarting
echo ========================================
echo.

call stop-server.bat
echo.
echo Waiting 2 seconds...
ping -n 3 127.0.0.1 >nul
echo.
call start-server.bat
