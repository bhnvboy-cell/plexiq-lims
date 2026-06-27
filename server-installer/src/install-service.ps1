<#
.SYNOPSIS
    Install PlexiQ LIMS as a Windows Background Service
.DESCRIPTION
    Uses NSSM (Non-Sucking Service Manager) to register the PHP server as a
    Windows service that starts automatically on boot.
#>

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Split-Path -Parent $ScriptDir
$nssmUrl = "https://nssm.cc/release/nssm-2.24.zip"
$nssmZip = "$env:TEMP\nssm.zip"
$nssmDir = "$AppDir\nssm"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PlexiQ LIMS - Windows Service Installer" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as admin
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[FAIL] Administrator privileges required!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'"
    exit 1
}

# Read port
$port = "8080"
$configFile = "$AppDir\config.ini"
if (Test-Path $configFile) {
    $config = Get-Content $configFile
    foreach ($line in $config) {
        if ($line -match '^PORT=(.+)') { $port = $matches[1] }
    }
}

# Check if PHP is installed
$php = "php"
if (Test-Path "$AppDir\php\php.exe") { $php = "$AppDir\php\php.exe" }
elseif (Test-Path "C:\xampp\php\php.exe") { $php = "C:\xampp\php\php.exe" }

# Download NSSM
Write-Host "[1/3] Downloading NSSM..." -ForegroundColor Yellow
try {
    Invoke-WebRequest -Uri $nssmUrl -OutFile $nssmZip -UseBasicParsing -ErrorAction Stop
    Expand-Archive -Path $nssmZip -DestinationPath $nssmDir -Force
    $nssmExe = Get-ChildItem -Path $nssmDir -Recurse -Filter "nssm.exe" | Select-Object -First 1 -ExpandProperty FullName
    Write-Host "[OK] NSSM downloaded: $nssmExe" -ForegroundColor Green
} catch {
    Write-Host "[FAIL] Could not download NSSM: $_" -ForegroundColor Red
    Write-Host "Download manually from: https://nssm.cc/download"
    exit 1
}

# Install service
Write-Host "[2/3] Installing Windows Service..." -ForegroundColor Yellow
$serviceName = "PlexiQLIMS"
$serviceExists = Get-Service -Name $serviceName -ErrorAction SilentlyContinue

if ($serviceExists) {
    Write-Host "  Service '$serviceName' already exists. Stopping and removing..." -ForegroundColor Gray
    & $nssmExe stop $serviceName 2>$null
    & $nssmExe remove $serviceName confirm 2>$null
    Start-Sleep -Seconds 2
}

& $nssmExe install $serviceName "`"$php`"" "-S 0.0.0.0:$port -t `"$AppDir\public`""
& $nssmExe set $serviceName DisplayName "PlexiQ LIMS Server"
& $nssmExe set $serviceName Description "PlexiQ Laboratory Information Management System"
& $nssmExe set $serviceName Start SERVICE_AUTO_START
& $nssmExe set $serviceName AppStdout "$AppDir\storage\logs\service-output.log"
& $nssmExe set $serviceName AppStderr "$AppDir\storage\logs\service-error.log"
& $nssmExe set $serviceName AppRotateFiles 1
& $nssmExe set $serviceName AppRotateBytes 10485760

Write-Host "[OK] Service installed" -ForegroundColor Green

# Start service
Write-Host "[3/3] Starting Service..." -ForegroundColor Yellow
& $nssmExe start $serviceName
Start-Sleep -Seconds 3

$svc = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($svc -and $svc.Status -eq 'Running') {
    Write-Host "[OK] PlexiQ LIMS Service is RUNNING on port $port" -ForegroundColor Green
    Write-Host "  URL: http://localhost:$port"
    Write-Host "  The service will start automatically on system boot."
} else {
    Write-Host "[WARN] Service installed but may not be running. Check Services panel." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "To manage the service:" -ForegroundColor White
Write-Host "  Start:    nssm start $serviceName"
Write-Host "  Stop:     nssm stop $serviceName"
Write-Host "  Restart:  nssm restart $serviceName"
Write-Host "  Remove:   remove-service.ps1"
Write-Host ""
pause
