@echo off
title Advanced LIMS - Database Setup
cd /d "%~dp0"

echo ========================================
echo   Advanced LIMS Database Setup
echo ========================================
echo.

REM Auto-detect psql if not in PATH
set PSQL_CMD=psql
where psql >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\Program Files\PostgreSQL\18\bin\psql.exe" set PSQL_CMD="C:\Program Files\PostgreSQL\18\bin\psql.exe"
    if exist "C:\Program Files\PostgreSQL\17\bin\psql.exe" set PSQL_CMD="C:\Program Files\PostgreSQL\17\bin\psql.exe"
    if exist "C:\Program Files\PostgreSQL\16\bin\psql.exe" set PSQL_CMD="C:\Program Files\PostgreSQL\16\bin\psql.exe"
    if exist "C:\Program Files (x86)\PostgreSQL\18\bin\psql.exe" set PSQL_CMD="C:\Program Files (x86)\PostgreSQL\18\bin\psql.exe"
    if exist "C:\Program Files (x86)\PostgreSQL\17\bin\psql.exe" set PSQL_CMD="C:\Program Files (x86)\PostgreSQL\17\bin\psql.exe"
    if exist "C:\Program Files (x86)\PostgreSQL\16\bin\psql.exe" set PSQL_CMD="C:\Program Files (x86)\PostgreSQL\16\bin\psql.exe"
)
if "%PSQL_CMD%"=="psql" (
    echo [ERROR] psql not found. Check these paths:
    echo   C:\Program Files\PostgreSQL\18\bin\psql.exe
    echo   C:\Program Files\PostgreSQL\17\bin\psql.exe
    echo.
    echo Or add your PostgreSQL bin folder to PATH and re-run.
    echo.
    pause
    exit /b 1
)

REM Read DB credentials from .env if available
set DB_HOST=127.0.0.1
set DB_PORT=5432
set DB_NAME=limsdb
set DB_USER=postgres

if exist ".env" (
    for /f "tokens=2 delims==" %%a in ('findstr /b "DB_HOST=" .env') do set DB_HOST=%%a
    for /f "tokens=2 delims==" %%a in ('findstr /b "DB_PORT=" .env') do set DB_PORT=%%a
    for /f "tokens=2 delims==" %%a in ('findstr /b "DB_DATABASE=" .env') do set DB_NAME=%%a
    for /f "tokens=2 delims==" %%a in ('findstr /b "DB_USERNAME=" .env') do set DB_USER=%%a
)

echo Using:
echo   Host: %DB_HOST%  Port: %DB_PORT%  DB: %DB_NAME%  User: %DB_USER%
echo   psql: %PSQL_CMD%
echo.

REM Accept overrides from command-line arguments
if not "%1"=="" set DB_HOST=%1
if not "%2"=="" set DB_PORT=%2
if not "%3"=="" set DB_NAME=%3
if not "%4"=="" set DB_USER=%4

REM Step 1: Create database
echo [1/2] Creating database "%DB_NAME%"...
%PSQL_CMD% -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -c "CREATE DATABASE \"%DB_NAME%\";" 2>nul
if %errorlevel% equ 0 (
    echo   [OK] Database created.
) else (
    echo   [INFO] Database may already exist. Continuing...
)

REM Step 2: Import schema
echo [2/2] Importing schema into "%DB_NAME%"...
%PSQL_CMD% -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d "%DB_NAME%" -f "database\schema.sql"
if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo   [SUCCESS] Database setup complete!
    echo ========================================
    echo   Host:     %DB_HOST%
    echo   Port:     %DB_PORT%
    echo   Database: %DB_NAME%
    echo   User:     %DB_USER%
    echo.
    echo   Login at http://localhost/plexiq
    echo   Default: admin / admin@123
    echo.
) else (
    echo.
    echo [ERROR] Schema import failed. Check errors above.
    echo.
    pause
    exit /b 1
)

pause
