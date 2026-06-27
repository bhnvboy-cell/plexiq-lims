@echo off
REM ============================================================
REM Advanced LIMS - Database Setup for XAMPP / Local PostgreSQL
REM ============================================================
cd /d "%~dp0"

echo Advanced LIMS - Database Initialization
echo ========================================
echo.

REM Find psql
set PSQL_CMD=psql
where psql >nul 2>&1
if %errorlevel% neq 0 (
    if exist "C:\Program Files\PostgreSQL\18\bin\psql.exe" set PSQL_CMD="C:\Program Files\PostgreSQL\18\bin\psql.exe"
    if exist "C:\Program Files (x86)\PostgreSQL\18\bin\psql.exe" set PSQL_CMD="C:\Program Files (x86)\PostgreSQL\18\bin\psql.exe"
)

echo Using: %PSQL_CMD%
echo.

set /p DB_HOST=PostgreSQL Host [127.0.0.1]: 
if "%DB_HOST%"=="" set DB_HOST=127.0.0.1

set /p DB_PORT=PostgreSQL Port [5432]: 
if "%DB_PORT%"=="" set DB_PORT=5432

set /p DB_NAME=Database Name [limsdb]: 
if "%DB_NAME%"=="" set DB_NAME=limsdb

set /p DB_USER=Username [postgres]: 
if "%DB_USER%"=="" set DB_USER=postgres

echo.
echo Step 1: Creating database...
%PSQL_CMD% -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -c "CREATE DATABASE %DB_NAME%;" 2>nul
if %errorlevel% equ 0 (echo [OK] Database created.) else (echo [WARN] Database may already exist.)

echo.
echo Step 2: Importing schema...
%PSQL_CMD% -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "database\schema.sql"
if %errorlevel% equ 0 (echo [OK] Schema imported.) else (echo [FAIL] Schema import failed! & pause & exit /b 1)

echo.
echo Step 3: Importing seed data...
%PSQL_CMD% -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "database\seed_data.sql"
if %errorlevel% equ 0 (echo [OK] Seed data imported.) else (echo [WARN] Seed data may have conflicts.)

echo.
echo ========================================
echo Database setup complete!
echo ========================================
echo Host:     %DB_HOST%:%DB_PORT%
echo Database: %DB_NAME%
echo 
echo Default login: admin / admin@123
echo.
pause
