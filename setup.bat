@echo off
title PlexiQ LIMS - Setup
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS - Database Setup
echo ========================================
echo.

REM Auto-detect psql
set PSQL=psql
where psql >nul 2>&1
if %errorlevel% neq 0 (
    if exist "C:\Program Files\PostgreSQL\18\bin\psql.exe" set PSQL="C:\Program Files\PostgreSQL\18\bin\psql.exe"
    if exist "C:\Program Files\PostgreSQL\17\bin\psql.exe" set PSQL="C:\Program Files\PostgreSQL\17\bin\psql.exe"
    if exist "C:\Program Files\PostgreSQL\16\bin\psql.exe" set PSQL="C:\Program Files\PostgreSQL\16\bin\psql.exe"
)

if "%PSQL%"=="psql" (
    echo [ERROR] psql not found. Install PostgreSQL or add it to PATH.
    pause
    exit /b 1
)

echo Using: %PSQL%
echo.
echo Step 1: Installing composer dependencies...
if exist composer.json (
    where composer >nul 2>&1
    if %errorlevel% equ 0 (
        composer install --no-interaction --quiet
        if %errorlevel% equ 0 (echo   [OK]) else (echo   [WARN] Composer install had issues)
    ) else (
        echo   [SKIP] Composer not found. Run 'composer install' manually.
    )
)

echo Step 2: Creating database...
%PSQL% -h 127.0.0.1 -p 5432 -U postgres -c "CREATE DATABASE limsdb;" 2>nul
if %errorlevel% equ 0 (echo   [OK] Database created.) else (echo   [INFO] Database may already exist.)

echo Step 3: Importing schema...
%PSQL% -h 127.0.0.1 -p 5432 -U postgres -d limsdb -f "database\schema.sql"
if %errorlevel% equ 0 (echo   [OK] Schema imported.) else (echo   [FAIL] Schema import failed! & pause & exit /b 1)

echo.
echo ========================================
echo   Setup complete!
echo ========================================
echo   Run start.bat to launch the server.
echo   Login: admin / admin@123
echo.
pause
