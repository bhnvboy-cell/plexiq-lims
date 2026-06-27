@echo off
title PlexiQ LIMS Server (Status)
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS Server - Status
echo ========================================
echo.

REM Read port from config
set PORT=8080
if exist config.ini (
    for /f "tokens=2 delims==" %%a in ('findstr /b "PORT=" config.ini') do set PORT=%%a
)

REM Check server status
tasklist /FI "WINDOWTITLE eq PlexiQ LIMS Server*" 2>nul | findstr /i "php.exe" >nul
if %errorlevel% equ 0 (
    echo [RUNNING] PlexiQ LIMS Server is active on port %PORT%
    echo   URL: http://localhost:%PORT%
    echo.
    echo   To stop:     stop-server.bat
    echo   To restart:  restart-server.bat
) else (
    echo [STOPPED] PlexiQ LIMS Server is not running.
    echo.
    echo   To start:    start-server.bat
)

echo.
pause
