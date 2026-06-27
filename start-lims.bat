@echo off
REM ============================================================
REM Advanced LIMS - Quick Start Script (Manual Deployment)
REM ============================================================
title Advanced LIMS Server
cd /d "%~dp0"

echo ========================================
echo   Advanced LIMS - Server Control
echo ========================================
echo.
echo Choose an option:
echo  [1] Start Apache + LIMS
echo  [2] Stop Apache
echo  [3] Restart Apache
echo  [4] Check Status
echo  [5] Initialize Database
echo  [6] Run SAP Sync
echo  [Q] Quit
echo.

:menu
set /p choice=Enter your choice: 

if "%choice%"=="1" goto start
if "%choice%"=="2" goto stop
if "%choice%"=="3" goto restart
if "%choice%"=="4" goto status
if "%choice%"=="5" goto initdb
if "%choice%"=="6" goto sapsync
if /i "%choice%"=="Q" goto end
goto menu

:start
echo.
echo Starting Apache server...
net start AdvancedLIMS 2>nul
if %errorlevel% equ 0 (
    echo [OK] Apache is running.
    echo [OK] Access LIMS at: http://localhost/lims
) else (
    echo [WARN] Apache service not installed. Installing...
    call :install_apache
)
goto end

:stop
echo.
net stop AdvancedLIMS 2>nul
echo [OK] Apache stopped.
goto end

:restart
echo.
net stop AdvancedLIMS 2>nul
net start AdvancedLIMS 2>nul
if %errorlevel% equ 0 (
    echo [OK] Apache restarted.
) else (
    echo [FAIL] Could not restart Apache.
)
goto end

:status
echo.
echo === System Status ===
net start | find "AdvancedLIMS" >nul
if %errorlevel% equ 0 (
    echo Apache: RUNNING
) else (
    echo Apache: STOPPED
)

if exist "C:\Program Files\PostgreSQL\18\bin\psql.exe" (
    echo PostgreSQL: INSTALLED
) else (
    echo PostgreSQL: NOT FOUND
)

if exist ".env" (
    echo .env: FOUND
) else (
    echo .env: MISSING
)

echo Vendor autoloader: 
if exist "vendor\autoload.php" (echo     OK) else (echo     MISSING - run: composer install)
echo.
goto end

:install_apache
echo Installing Apache service...
if exist "C:\LIMS\apache\bin\httpd.exe" (
    C:\LIMS\apache\bin\httpd.exe -k install -n "AdvancedLIMS"
    net start AdvancedLIMS
    echo [OK] Apache installed and started.
) else (
    echo [FAIL] Apache binary not found at C:\LIMS\apache\bin\httpd.exe
)
goto end

:initdb
echo.
echo Initializing database...
if exist "installer\scripts\import-database.bat" (
    call installer\scripts\import-database.bat C:\LIMS
) else (
    echo [WARN] Database script not found.
    echo Import manually:
    echo   psql -U postgres -c "CREATE DATABASE limsdb;"
    echo   psql -U postgres -d limsdb -f database\schema.sql
    echo   psql -U postgres -d limsdb -f database\seed_data.sql
)
goto end

:sapsync
echo.
echo Running SAP HANA Sync...
php bin\console sap:sync
echo.
pause
goto end

:end
echo.
pause
