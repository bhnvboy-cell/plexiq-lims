@echo off
title PlexiQ LIMS - Validate Installation
cd /d "%~dp0"

echo ========================================
echo   PlexiQ LIMS - Validate Installation
echo ========================================
echo.

powershell -ExecutionPolicy Bypass -File "validate-install.ps1"
if %errorlevel% neq 0 (
    echo.
    pause
    exit /b 1
)
