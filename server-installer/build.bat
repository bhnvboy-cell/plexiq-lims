@echo off
title PlexiQ LIMS - Installer Builder
cd /d "%~dp0"

setlocal enabledelayedexpansion

echo ========================================
echo   PlexiQ LIMS - Installer Builder
echo ========================================
echo.
echo This builds a Windows installer for the PlexiQ LIMS Server.
echo.
echo Methods (in order of preference):
echo   1. Inno Setup (professional EXE)
echo   2. ps2exe (PowerShell to EXE)
echo   3. .NET native bootstrapper (uses built-in csc.exe)
echo   4. Run install.ps1 directly (no EXE needed)
echo.

REM Step 1: Check prerequisites
echo [1/4] Checking prerequisites...

REM Option A: Inno Setup
set ISCC=
where ISCC.exe >nul 2>&1
if %errorlevel% equ 0 (
    set ISCC=ISCC.exe
) else (
    for %%P in (
        "C:\Program Files (x86)\Inno Setup 6\ISCC.exe"
        "C:\Program Files\Inno Setup 6\ISCC.exe"
    ) do (
        if exist %%P set ISCC=%%P
    )
)

if defined ISCC (
    echo   [FOUND] Inno Setup: %ISCC%
    goto :build_innosetup
)

REM Option B: ps2exe
where ps2exe >nul 2>&1
if %errorlevel% equ 0 (
    echo   [FOUND] ps2exe module
    goto :build_powershell
)

REM Option C: .NET compiler
set CSC=
where csc.exe >nul 2>&1
if %errorlevel% equ 0 (
    set CSC=csc.exe
) else (
    for %%P in (
        "C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe"
        "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
    ) do (
        if exist %%P set CSC=%%P
    )
)

if defined CSC (
    echo   [FOUND] .NET compiler: %CSC%
    goto :build_dotnet
)

echo   [INFO] No EXE builder found. Will use install.ps1 directly.
goto :build_direct

REM ============================================================
REM Build Method 1: Inno Setup (best quality)
REM ============================================================
:build_innosetup
echo.
echo [2/4] Validating source files...
if not exist "..\public\index.php" (
    echo   [FAIL] Missing source files. Run from project root: server-installer\
    pause
    exit /b 1
)
echo   [OK] Source files validated.

echo [3/4] Generating installer assets...
if exist "assets\generate-logo.ps1" (
    powershell -ExecutionPolicy Bypass -File "assets\generate-logo.ps1" >nul 2>&1
    echo   [OK] Assets generated.
)

echo [4/4] Compiling with Inno Setup...
echo   This may take 30-60 seconds...
%ISCC% "setup.iss"
if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo   SUCCESS: Installer built with Inno Setup
    echo ========================================
    for %%F in ("Output\PlexiQ-LIMS-Server-Setup-*.exe") do (
        echo   Output: %%F
    )
    echo.
    echo Run the EXE on target server as Administrator.
) else (
    echo   [FAIL] Inno Setup compilation failed.
)
goto :end

REM ============================================================
REM Build Method 2: PowerShell build script
REM ============================================================
:build_powershell
echo.
echo [2/4] Building with PowerShell + ps2exe...
powershell -ExecutionPolicy Bypass -File "build-exe.ps1" -Method ps2exe
if %errorlevel% equ 0 (
    echo.
    echo   Build completed. See Output\ folder.
)
goto :end

REM ============================================================
REM Build Method 3: .NET native bootstrapper
REM ============================================================
:build_dotnet
echo.
echo [2/4] Building with .NET native bootstrapper...
powershell -ExecutionPolicy Bypass -File "build-exe.ps1" -Method net
if %errorlevel% equ 0 (
    echo.
    echo   Build completed. See Output\ folder.
)
goto :end

REM ============================================================
REM Build Method 4: Direct install.ps1 usage
REM ============================================================
:build_direct
echo.
echo [2/2] No EXE builder tools found.
echo.
echo ========================================
echo   To install, run this command as Administrator
echo   on the target server:
echo ========================================
echo.
echo   powershell -ExecutionPolicy Bypass -File "install.ps1"
echo.
echo This will launch the GUI installer wizard.
echo The script requires only PowerShell 5.1+ (built into Windows 10+).
echo.
echo Alternatively, install one of these tools and rebuild:
echo   - Inno Setup 6: https://jrsoftware.org/isdl.php
echo   - ps2exe: Install-Module -Name ps2exe
echo.
goto :end

:end
echo.
pause
