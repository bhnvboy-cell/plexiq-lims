@echo off
title PlexiQ LIMS - Uninstall
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS - Uninstall
echo ========================================
echo.

set /p CONFIRM=Type UNINSTALL to confirm: 
if /i not "%CONFIRM%"=="UNINSTALL" (
    echo Cancelled.
    pause
    exit /b 0
)

echo.
echo Step 1: Dropping database...
set PSQL=psql
where psql >nul 2>&1
if %errorlevel% equ 0 (
    %PSQL% -h 127.0.0.1 -U postgres -c "DROP DATABASE IF EXISTS limsdb;" 2>nul
    if %errorlevel% equ 0 (echo   [OK] Database dropped.) else (echo   [WARN] Could not drop database.)
) else (
    echo   [SKIP] psql not found. Drop manually: psql -U postgres -c "DROP DATABASE limsdb;"
)

echo Step 2: Removing vendor directory...
if exist vendor (
    rmdir /s /q vendor >nul 2>&1
    echo   [OK] vendor removed.
)

echo Step 3: Clearing session files...
if exist storage\sessions (
    del /q storage\sessions\* >nul 2>&1
    echo   [OK] Sessions cleared.
)

echo.
echo ========================================
echo   Uninstall complete.
echo ========================================
pause
