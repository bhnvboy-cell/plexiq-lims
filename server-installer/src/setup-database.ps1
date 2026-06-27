<#
.SYNOPSIS
    PlexiQ LIMS - Database Setup Script
.DESCRIPTION
    Creates the LIMS database and imports the schema.
    Run this after installation to set up the database.
#>

param(
    [string]$Host = "127.0.0.1",
    [string]$Port = "5432",
    [string]$Database = "limsdb",
    [string]$User = "postgres",
    [string]$Password = ""
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Split-Path -Parent $ScriptDir

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PlexiQ LIMS - Database Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Find psql
$psqlPaths = @(
    "C:\Program Files\PostgreSQL\18\bin\psql.exe",
    "C:\Program Files\PostgreSQL\17\bin\psql.exe",
    "C:\Program Files\PostgreSQL\16\bin\psql.exe",
    "C:\Program Files\PostgreSQL\15\bin\psql.exe",
    "C:\Program Files\PostgreSQL\14\bin\psql.exe"
)

$psql = $null
foreach ($p in $psqlPaths) {
    if (Test-Path $p) { $psql = $p; break }
}

if (-not $psql) {
    # Try PATH
    $psql = (Get-Command "psql" -ErrorAction SilentlyContinue).Source
}

if (-not $psql) {
    Write-Host "[FAIL] PostgreSQL (psql) not found!" -ForegroundColor Red
    Write-Host "Please install PostgreSQL 14+ and ensure psql is in your PATH."
    Write-Host "Download: https://www.postgresql.org/download/windows/"
    exit 1
}

Write-Host "[INFO] Using psql: $psql" -ForegroundColor Gray

# Build connection string
$connArgs = "-h $Host -p $Port -U $User"
if ($Password) {
    $env:PGPASSWORD = $Password
}

Write-Host "[1/3] Checking database connection..." -ForegroundColor Yellow
$testCmd = "& `"$psql`" $connArgs -c 'SELECT 1' -q 2>&1"
$testResult = Invoke-Expression $testCmd
if ($LASTEXITCODE -ne 0) {
    Write-Host "[FAIL] Cannot connect to PostgreSQL at $Host`:$Port" -ForegroundColor Red
    Write-Host "  Check that PostgreSQL is running and credentials are correct."
    exit 1
}
Write-Host "[OK] Connection successful" -ForegroundColor Green

# Check if database exists, create if not
Write-Host "[2/3] Checking database '$Database'..." -ForegroundColor Yellow
$checkCmd = "& `"$psql`" $connArgs -lqt 2>&1 | Select-String -Pattern '^ $Database '"
$dbExists = Invoke-Expression $checkCmd

if (-not $dbExists) {
    Write-Host "  Database '$Database' does not exist. Creating..." -ForegroundColor Gray
    $createCmd = "& `"$psql`" $connArgs -c 'CREATE DATABASE $Database;' 2>&1"
    Invoke-Expression $createCmd
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[FAIL] Could not create database '$Database'" -ForegroundColor Red
        exit 1
    }
    Write-Host "[OK] Database '$Database' created" -ForegroundColor Green
} else {
    Write-Host "[SKIP] Database '$Database' already exists" -ForegroundColor Green
}

# Import schema
$schemaFiles = @(
    "database\schema.sql"
)

$migrationDir = "database\migrations"
if (Test-Path "$AppDir\$migrationDir") {
    $migrationFiles = Get-ChildItem "$AppDir\$migrationDir" -Filter "*.sql" | Sort-Object Name
    $schemaFiles += $migrationFiles.FullName | ForEach-Object { $_.Substring($AppDir.Length + 1) }
}

Write-Host "[3/3] Importing schema and migrations..." -ForegroundColor Yellow
foreach ($file in $schemaFiles) {
    $fullPath = "$AppDir\$file"
    if (Test-Path $fullPath) {
        Write-Host "  Running: $file" -ForegroundColor Gray
        $importCmd = "& `"$psql`" $connArgs -d $Database -f `"$fullPath`" -q 2>&1"
        $result = Invoke-Expression $importCmd
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  [WARN] Error in $file (may be non-fatal): $result" -ForegroundColor Yellow
        } else {
            Write-Host "  [OK] $file imported" -ForegroundColor Green
        }
    } else {
        Write-Host "  [SKIP] $file not found" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Database setup complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Database: $Database"
Write-Host "  Host:     $Host`:$Port"
Write-Host "  User:     $User"
Write-Host ""
Write-Host "  Default login: admin / admin@123"
Write-Host ""

if ($Password) { Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue }
