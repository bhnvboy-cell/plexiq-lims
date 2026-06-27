<#
.SYNOPSIS
    Remove PlexiQ LIMS Windows Service
.DESCRIPTION
    Stops and removes the PlexiQ LIMS Windows service.
#>

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Split-Path -Parent $ScriptDir
$nssmExe = Get-ChildItem -Path "$AppDir\nssm" -Recurse -Filter "nssm.exe" | Select-Object -First 1 -ExpandProperty FullName

$serviceName = "PlexiQLIMS"
$serviceExists = Get-Service -Name $serviceName -ErrorAction SilentlyContinue

if (-not $serviceExists) {
    Write-Host "[INFO] Service '$serviceName' is not installed." -ForegroundColor Yellow
    exit 0
}

Write-Host "Stopping and removing PlexiQ LIMS Service..." -ForegroundColor Yellow
if ($nssmExe -and (Test-Path $nssmExe)) {
    & $nssmExe stop $serviceName 2>$null
    Start-Sleep 2
    & $nssmExe remove $serviceName confirm
} else {
    sc.exe stop $serviceName 2>$null
    Start-Sleep 2
    sc.exe delete $serviceName
}

$check = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if (-not $check) {
    Write-Host "[OK] Service removed successfully." -ForegroundColor Green
} else {
    Write-Host "[WARN] Service may still exist. Check Services panel." -ForegroundColor Yellow
}

pause
