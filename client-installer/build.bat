@echo off
title PlexiQ LIMS - Client Installer Builder
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS - Client Installer Builder
echo ========================================
echo.

REM Generate logo and icon assets
echo [1/3] Generating installer assets...
powershell -ExecutionPolicy Bypass -File "assets\generate-logo.ps1"
if %errorlevel% neq 0 (
    echo   [FAIL] Asset generation failed.
    pause
    exit /b 1
)
echo.

REM Find Inno Setup
echo [2/3] Checking for Inno Setup...
set ISCC=ISCC.exe
where ISCC.exe >nul 2>&1
if %errorlevel% neq 0 (
    if exist "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" set ISCC="C:\Program Files (x86)\Inno Setup 6\ISCC.exe"
    if exist "C:\Program Files\Inno Setup 6\ISCC.exe" set ISCC="C:\Program Files\Inno Setup 6\ISCC.exe"
    if exist "C:\Program Files (x86)\Inno Setup 5\ISCC.exe" set ISCC="C:\Program Files (x86)\Inno Setup 5\ISCC.exe"
    if exist "C:\Program Files\Inno Setup 5\ISCC.exe" set ISCC="C:\Program Files\Inno Setup 5\ISCC.exe"
)

echo   Using: %ISCC%
echo.

REM Compile installer
echo [3/3] Compiling installer...
echo   This may take a few seconds...
%ISCC% "setup.iss"
if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo   Installer built successfully!
    echo ========================================
    echo   Output: Output\PlexiQ-LIMS-Client-Setup.exe
    echo.
) else (
    echo.
    echo   [FAIL] Compilation failed.
    echo   Ensure Inno Setup 6 is installed (https://jrsoftware.org/isdl.php)
    echo.
)

pause
