<#
.SYNOPSIS
    PlexiQ LIMS - Post-Installation Validation
.DESCRIPTION
    Validates that the PlexiQ LIMS installation is complete and working.
    Checks: directory structure, PHP, PostgreSQL, database, web server, config.
.PARAMETER AppPath
    Root path of the PlexiQ LIMS installation. Auto-detected if not specified.
#>

param(
    [string]$AppPath = ""
)

if (-not $AppPath) {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    # Check if we're in src\ or directly in app root
    if ((Split-Path -Leaf $ScriptDir) -eq "src") {
        $AppDir = Split-Path -Parent $ScriptDir
        if ((Split-Path -Leaf $AppDir) -eq "server-installer") {
            # Running from build directory - skip file checks
            $AppDir = Split-Path -Parent $AppDir
        }
    } else {
        $AppDir = $ScriptDir
    }
} else {
    $AppDir = $AppPath
}

$passed = 0; $failed = 0; $warnings = 0

function Write-Check {
    param([string]$Name, [bool]$Result, [string]$Detail)
    if ($Result) { Write-Host "  [PASS] $Name" -ForegroundColor Green; $script:passed++ }
    else { Write-Host "  [FAIL] $Name" -ForegroundColor Red; if ($Detail) { Write-Host "         $Detail" -ForegroundColor Gray }; $script:failed++ }
}

function Write-Warn {
    param([string]$Name, [string]$Detail)
    Write-Host "  [WARN] $Name" -ForegroundColor Yellow
    if ($Detail) { Write-Host "         $Detail" -ForegroundColor Gray }
    $script:warnings++
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PlexiQ LIMS - Installation Validation" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Installation Path: $AppDir" -ForegroundColor White
Write-Host ""

# ============================================================
# 1. Directory Structure
# ============================================================
Write-Host "[1/6] Directory Structure" -ForegroundColor Yellow
$dirs = @("public", "app", "config", "database", "resources", "routes", "storage")
foreach ($d in $dirs) {
    Write-Check -Name "Directory: $d" -Result (Test-Path "$AppDir\$d")
}

# Check vendor separately (may not exist without composer install)
if (Test-Path "$AppDir\vendor\autoload.php") {
    Write-Check -Name "Vendor (autoloader)" -Result $true
} else {
    Write-Warn -Name "Vendor directory" -Detail "Run 'composer install' in $AppDir"
}

# ============================================================
# 2. Core Files
# ============================================================
Write-Host "[2/6] Core Files" -ForegroundColor Yellow
$coreFiles = @(
    @{Name="Entry Point (index.php)"; Path="public\index.php"},
    @{Name="Router (Router.php)"; Path="app\Router.php"},
    @{Name="Database Schema"; Path="database\schema.sql"},
    @{Name="Web Routes"; Path="routes\web.php"},
    @{Name="Helpers"; Path="app\Helpers\helpers.php"}
)
$allCoreOk = $true
foreach ($f in $coreFiles) {
    $ok = Test-Path "$AppDir\$($f.Path)"
    if (-not $ok) { $allCoreOk = $false }
    Write-Check -Name $f.Name -Result $ok
}

if (Test-Path "$AppDir\.env") {
    Write-Check -Name "Environment Config (.env)" -Result $true
} else {
    Write-Warn -Name "Environment Config (.env)" -Detail "Will be created by installer"
}

# ============================================================
# 3. PHP Runtime
# ============================================================
Write-Host "[3/6] PHP Runtime" -ForegroundColor Yellow

$phpPaths = @("$AppDir\php\php.exe", "C:\xampp\php\php.exe", "C:\php\php.exe")
$php = $null
foreach ($p in $phpPaths) { if (Test-Path $p) { $php = $p; break } }
if (-not $php) { $php = (Get-Command "php" -ErrorAction SilentlyContinue).Source }

if ($php) {
    $version = & $php -v 2>&1 | Select-Object -First 1
    Write-Check -Name "PHP Found: $php" -Result $true -Detail $version

    $verStr = & $php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"
    $verParts = $verStr -split '\.'
    $phpMinVerOk = ([int]$verParts[0] -ge 8)
    Write-Check -Name "PHP 8.0+ (v$verStr)" -Result $phpMinVerOk -Detail "Required: 8.0+"

    $extensions = @("pdo_pgsql", "json", "session", "mbstring")
    foreach ($ext in $extensions) {
        $loaded = & $php -r "echo extension_loaded('$ext') ? '1' : '0';" 2>$null
        Write-Check -Name "Extension: $ext" -Result ($loaded -eq '1')
    }

    $indexFile = "$AppDir\public\index.php"
    if (Test-Path $indexFile) {
        $lintResult = & $php -l $indexFile 2>&1
        Write-Check -Name "PHP Syntax (index.php)" -Result ($lintResult -like "*No syntax errors*")
    }
} else {
    Write-Check -Name "PHP Not Found" -Result $false -Detail "Install PHP 8.0+ and add to PATH"
}

# ============================================================
# 4. PostgreSQL Database
# ============================================================
Write-Host "[4/6] PostgreSQL Database" -ForegroundColor Yellow

$psqlPaths = @(
    "C:\Program Files\PostgreSQL\18\bin\psql.exe",
    "C:\Program Files\PostgreSQL\17\bin\psql.exe",
    "C:\Program Files\PostgreSQL\16\bin\psql.exe",
    "C:\Program Files\PostgreSQL\15\bin\psql.exe",
    "C:\Program Files\PostgreSQL\14\bin\psql.exe"
)
$psql = $null
foreach ($p in $psqlPaths) { if (Test-Path $p) { $psql = $p; break } }
if (-not $psql) { $psql = (Get-Command "psql" -ErrorAction SilentlyContinue).Source }

if ($psql) {
    $psqlVersion = (& $psql --version 2>&1) -join ' '
    Write-Check -Name "PostgreSQL Found: $psql" -Result $true -Detail $psqlVersion

    $envFile = "$AppDir\.env"
    $dbHost = "127.0.0.1"; $dbPort = "5432"; $dbName = "limsdb"; $dbUser = "postgres"; $dbPass = ""
    if (Test-Path $envFile) {
        Get-Content $envFile | ForEach-Object {
            if ($_ -match '^DB_HOST=(.+)') { $dbHost = $matches[1] }
            if ($_ -match '^DB_PORT=(.+)') { $dbPort = $matches[1] }
            if ($_ -match '^DB_DATABASE=(.+)') { $dbName = $matches[1] }
            if ($_ -match '^DB_USERNAME=(.+)') { $dbUser = $matches[1] }
            if ($_ -match '^DB_PASSWORD=(.*)') { $dbPass = $matches[1] }
        }
    }

    $env:PGPASSWORD = $dbPass
    $testResult = & $psql -h $dbHost -p $dbPort -U $dbUser -d $dbName -c "SELECT 1 AS ok" -t -q 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Check -Name "DB Connection ($dbName@$dbHost`:$dbPort)" -Result $true

        $tablesRaw = & $psql -h $dbHost -p $dbPort -U $dbUser -d $dbName -t -A -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public'" 2>&1
        $tableCount = $tablesRaw.Trim()
        if ($tableCount -match '^\d+$') {
            $tablesOk = ([int]$tableCount -ge 10)
            Write-Check -Name "Database Tables ($tableCount found)" -Result $tablesOk -Detail "Expected: 10+ tables"
        } else {
            Write-Warn -Name "Could not count tables" -Detail $tableCount
        }

        $adminRaw = & $psql -h $dbHost -p $dbPort -U $dbUser -d $dbName -t -A -c "SELECT COUNT(*) FROM users WHERE username='admin'" 2>&1
        $adminCount = $adminRaw.Trim()
        if ($adminCount -match '^\d+$') {
            Write-Check -Name "Default Admin User" -Result ([int]$adminCount -ge 1)
        } else {
            Write-Warn -Name "Could not check admin user" -Detail $adminRaw
        }
    } else {
        Write-Check -Name "DB Connection ($dbName@$dbHost`:$dbPort)" -Result $false -Detail ($testResult -join ' ')
    }
    Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
} else {
    Write-Warn -Name "PostgreSQL" -Detail "psql not found. Install PostgreSQL 14+ and ensure it's in PATH"
}

# ============================================================
# 5. Web Server
# ============================================================
Write-Host "[5/6] Web Server Check" -ForegroundColor Yellow

$port = "8080"
$envFile = "$AppDir\.env"
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^SERVER_PORT=(.+)') { $port = $matches[1] }
    }
}

try {
    $response = Invoke-WebRequest "http://127.0.0.1:$port/login" -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Check -Name "Web Server (http://127.0.0.1:$port)" -Result $true -Detail "HTTP $($response.StatusCode), $($response.Content.Length)B"
} catch {
    $procs = Get-Process -Name "php" -ErrorAction SilentlyContinue
    $matching = $procs | Where-Object { $_.CommandLine -like "*$port*" }
    if ($matching) {
        Write-Warn -Name "Web Server Check" -Detail "PHP process exists on port $port but HTTP unavailable ($($_.Exception.Message))"
    } else {
        Write-Warn -Name "Web Server" -Detail "Not running. Start with start-server.bat or run as service"
    }
}

# ============================================================
# 6. Configuration
# ============================================================
Write-Host "[6/6] Configuration" -ForegroundColor Yellow
Write-Check -Name ".env file exists" -Result (Test-Path "$AppDir\.env")
Write-Check -Name "Storage directory" -Result (Test-Path "$AppDir\storage")

if (-not (Test-Path "$AppDir\storage\sessions")) { New-Item -Path "$AppDir\storage\sessions" -ItemType Directory -Force -ErrorAction SilentlyContinue | Out-Null }
Write-Check -Name "Sessions directory" -Result (Test-Path "$AppDir\storage\sessions")

if (-not (Test-Path "$AppDir\storage\logs")) { New-Item -Path "$AppDir\storage\logs" -ItemType Directory -Force -ErrorAction SilentlyContinue | Out-Null }
Write-Check -Name "Logs directory" -Result (Test-Path "$AppDir\storage\logs")

# ============================================================
# Summary
# ============================================================
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Validation Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($failed -eq 0 -and $warnings -eq 0) {
    Write-Host "  All checks passed! Installation is complete." -ForegroundColor Green
} elseif ($failed -eq 0 -and $warnings -gt 0) {
    Write-Host "  Passed: $passed  Warnings: $warnings  Failed: $failed" -ForegroundColor Yellow
    Write-Host "  Installation functional with warnings." -ForegroundColor Yellow
} else {
    Write-Host "  Passed: $passed  Warnings: $warnings  Failed: $failed" -ForegroundColor Red
    Write-Host "  Some checks failed. See details above." -ForegroundColor Red
}

Write-Host ""
Write-Host "  Default Login: admin / admin@123" -ForegroundColor White
Write-Host "  Customer Login: customer / admin@123" -ForegroundColor White
Write-Host ""
