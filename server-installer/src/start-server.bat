@echo off
title PlexiQ LIMS Server (Starting...)
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS Server - Starting
echo ========================================
echo.

REM Read port from config
set PORT=8080
if exist config.ini (
    for /f "tokens=2 delims==" %%a in ('findstr /b "PORT=" config.ini') do set PORT=%%a
)

REM Check if already running
tasklist /FI "WINDOWTITLE eq PlexiQ LIMS Server*" 2>nul | findstr /i "php.exe" >nul
if %errorlevel% equ 0 (
    echo [INFO] PlexiQ LIMS Server is already running on port %PORT%
    echo.
    echo   Open: http://localhost:%PORT%
    echo.
    pause
    exit /b 0
)

REM Find PHP
set PHP=php
if exist config.ini (
    for /f "tokens=2 delims==" %%a in ('findstr /b "PHP_PATH=" config.ini') do set PHP=%%a
)
if "%PHP%"=="php" (
    where php >nul 2>&1
    if %errorlevel% neq 0 (
        if exist "C:\xampp\php\php.exe" set PHP=C:\xampp\php\php.exe
        if exist "php\php.exe" set PHP=php\php.exe
    )
)

echo [1/2] Using PHP: %PHP%
echo [2/2] Starting server on port %PORT%...
echo.

title PlexiQ LIMS Server (Port %PORT%)
"%PHP%" -S 0.0.0.0:%PORT% -t "%~dp0public"

echo.
echo Server stopped.
pause
