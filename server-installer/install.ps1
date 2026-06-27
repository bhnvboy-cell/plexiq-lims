<#
.SYNOPSIS
    PlexiQ LIMS - Standalone PowerShell Installer
.DESCRIPTION
    GUI-based installer for PlexiQ LIMS Server. Works on any Windows
    machine with PowerShell 5.1+ — no external tools required.
    Can be wrapped into an EXE using build-exe.ps1 or IExpress.
#>

#Requires -Version 5.1

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppSource = $ScriptDir  # Source of application files (script location)
$Version = "2.0"

# Default settings
$global:InstallPort = "8080"
$global:InstallPath = "$env:ProgramFiles\PlexiQ-LIMS"
$global:DbHost = "127.0.0.1"
$global:DbPort = "5432"
$global:DbName = "limsdb"
$global:DbUser = "postgres"
$global:DbPass = ""
$global:CreateDesktopShortcut = $true
$global:CreateStartMenuShortcut = $true
$global:StartServerAfterInstall = $true

# ============================================================
# WIZARD FORM
# ============================================================
$form = New-Object System.Windows.Forms.Form
$form.Text = "PlexiQ LIMS Server $Version — Installer"
$form.Size = New-Object System.Drawing.Size(640, 520)
$form.StartPosition = "CenterScreen"
$form.FormBorderStyle = "FixedDialog"
$form.MaximizeBox = $false
$form.Icon = if (Test-Path "$ScriptDir\assets\icon.ico") { [System.Drawing.Icon]::ExtractAssociatedIcon("$ScriptDir\assets\icon.ico") } else { $null }

# Header panel
$header = New-Object System.Windows.Forms.Panel
$header.Size = New-Object System.Drawing.Size(640, 80)
$header.BackColor = [System.Drawing.Color]::FromArgb(13, 110, 253)
$header.Dock = "Top"

$logoLabel = New-Object System.Windows.Forms.Label
$logoLabel.Text = "PlexiQ LIMS"
$logoLabel.Font = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Bold)
$logoLabel.ForeColor = [System.Drawing.Color]::White
$logoLabel.Size = New-Object System.Drawing.Size(400, 40)
$logoLabel.Location = New-Object System.Drawing.Point(20, 15)
$header.Controls.Add($logoLabel)

$subLabel = New-Object System.Windows.Forms.Label
$subLabel.Text = "Laboratory Information Management System v$Version"
$subLabel.Font = New-Object System.Drawing.Font("Segoe UI", 10)
$subLabel.ForeColor = [System.Drawing.Color]::FromArgb(200, 220, 255)
$subLabel.Size = New-Object System.Drawing.Size(400, 20)
$subLabel.Location = New-Object System.Drawing.Point(22, 52)
$header.Controls.Add($subLabel)
$form.Controls.Add($header)

# Tab control for wizard steps
$tabControl = New-Object System.Windows.Forms.TabControl
$tabControl.Size = New-Object System.Drawing.Size(620, 340)
$tabControl.Location = New-Object System.Drawing.Point(10, 90)
$tabControl.Appearance = "Buttons"
$tabControl.ItemSize = New-Object System.Drawing.Size(150, 30)

# Tab 1: Welcome
$tabWelcome = New-Object System.Windows.Forms.TabPage
$tabWelcome.Text = "Welcome"
$welcomeBox = New-Object System.Windows.Forms.RichTextBox
$welcomeBox.Size = New-Object System.Drawing.Size(590, 280)
$welcomeBox.Location = New-Object System.Drawing.Point(10, 10)
$welcomeBox.ReadOnly = $true
$welcomeBox.BackColor = [System.Drawing.Color]::White
$welcomeBox.Text = @"
Welcome to PlexiQ LIMS Server Installation

This installer will deploy the PlexiQ LIMS server on your Windows machine.

Prerequisites:
  • PostgreSQL 14 or later (must be installed and running)
  • PHP 8.0 or later with extensions: pdo_pgsql, json, session, mbstring
  • Windows 10/11 or Windows Server 2016+

The installer will:
  1. Copy application files to your chosen directory
  2. Configure database connection
  3. Create the database and import schema
  4. Create Start Menu and Desktop shortcuts
  5. Start the LIMS server

Default login after installation:
  Admin:    admin / admin@123
  Customer: customer / admin@123

Click "Settings" to configure, then "Install" to begin.
"@
$tabWelcome.Controls.Add($welcomeBox)
$tabControl.Controls.Add($tabWelcome)

# Tab 2: Settings
$tabSettings = New-Object System.Windows.Forms.TabPage
$tabSettings.Text = "Settings"

$y = 15
$label = New-Object System.Windows.Forms.Label
$label.Text = "Installation Settings"
$label.Font = New-Object System.Drawing.Font("Segoe UI", 11, [System.Drawing.FontStyle]::Bold)
$label.Size = New-Object System.Drawing.Size(300, 25)
$label.Location = New-Object System.Drawing.Point(10, $y)
$tabSettings.Controls.Add($label)

$y += 35
$lbl = New-Object System.Windows.Forms.Label; $lbl.Text = "Install Path:"; $lbl.Location = New-Object Drawing.Point(10, $y+3); $lbl.Size = New-Object Drawing.Size(120, 20)
$txt = New-Object System.Windows.Forms.TextBox; $txt.Text = $global:InstallPath; $txt.Location = New-Object Drawing.Point(140, $y); $txt.Size = New-Object Drawing.Size(350, 22); $txt.Add_TextChanged({ $global:InstallPath = $this.Text })
$btnBrowse = New-Object System.Windows.Forms.Button; $btnBrowse.Text = "..."; $btnBrowse.Location = New-Object Drawing.Point(500, $y); $btnBrowse.Size = New-Object Drawing.Size(40, 25)
$btnBrowse.Add_Click({ $f = New-Object Windows.Forms.FolderBrowserDialog; $f.SelectedPath = $global:InstallPath; if ($f.ShowDialog() -eq "OK") { $txt.Text = $f.SelectedPath } })
$tabSettings.Controls.AddRange(@($lbl, $txt, $btnBrowse))

$y += 35
$lbl2 = New-Object Windows.Forms.Label; $lbl2.Text = "Server Port:"; $lbl2.Location = New-Object Drawing.Point(10, $y+3); $lbl2.Size = New-Object Drawing.Size(120, 20)
$txt2 = New-Object Windows.Forms.TextBox; $txt2.Text = $global:InstallPort; $txt2.Location = New-Object Drawing.Point(140, $y); $txt2.Size = New-Object Drawing.Size(80, 22); $txt2.Add_TextChanged({ $global:InstallPort = $this.Text })
$tabSettings.Controls.AddRange(@($lbl2, $txt2))

$y += 40
$label2 = New-Object Windows.Forms.Label
$label2.Text = "Database Connection"
$label2.Font = New-Object Drawing.Font("Segoe UI", 11, [Drawing.FontStyle]::Bold)
$label2.Size = New-Object Drawing.Size(300, 25)
$label2.Location = New-Object Drawing.Point(10, $y)
$tabSettings.Controls.Add($label2)

$fields = @(
    @{l="Host:"; prop='DbHost'},
    @{l="Port:"; prop='DbPort'},
    @{l="Database:"; prop='DbName'},
    @{l="User:"; prop='DbUser'},
    @{l="Password:"; prop='DbPass'}
)
foreach ($f in $fields) {
    $y += 30
    $l = New-Object Windows.Forms.Label; $l.Text = $f.l; $l.Location = New-Object Drawing.Point(10, $y+3); $l.Size = New-Object Drawing.Size(120, 20)
    $t = New-Object Windows.Forms.TextBox; $t.Text = $global[$f.prop]; $t.Location = New-Object Drawing.Point(140, $y); $t.Size = New-Object Drawing.Size(350, 22)
    if ($f.prop -eq 'DbPass') { $t.UseSystemPasswordChar = $true }
    $t.Add_TextChanged({ Set-Variable -Name $f.prop -Value $this.Text -Scope Global })
    switch ($f.prop) { 'DbHost' { $t.Add_TextChanged({ $global:DbHost = $this.Text }) }; 'DbPort' { $t.Add_TextChanged({ $global:DbPort = $this.Text }) }; 'DbName' { $t.Add_TextChanged({ $global:DbName = $this.Text }) }; 'DbUser' { $t.Add_TextChanged({ $global:DbUser = $this.Text }) }; 'DbPass' { $t.Add_TextChanged({ $global:DbPass = $this.Text }) } }
    $tabSettings.Controls.AddRange(@($l, $t))
}

$y += 35
$chkDesktop = New-Object Windows.Forms.CheckBox; $chkDesktop.Text = "Create Desktop shortcut"; $chkDesktop.Checked = $true; $chkDesktop.Location = New-Object Drawing.Point(10, $y); $chkDesktop.Size = New-Object Drawing.Size(200, 25)
$chkDesktop.Add_CheckedChanged({ $global:CreateDesktopShortcut = $this.Checked })
$tabSettings.Controls.Add($chkDesktop)

$y += 25
$chkStart = New-Object Windows.Forms.CheckBox; $chkStart.Text = "Create Start Menu shortcut"; $chkStart.Checked = $true; $chkStart.Location = New-Object Drawing.Point(10, $y); $chkStart.Size = New-Object Drawing.Size(200, 25)
$chkStart.Add_CheckedChanged({ $global:CreateStartMenuShortcut = $this.Checked })
$tabSettings.Controls.Add($chkStart)

$y += 25
$chkStartSrv = New-Object Windows.Forms.CheckBox; $chkStartSrv.Text = "Start server after installation"; $chkStartSrv.Checked = $true; $chkStartSrv.Location = New-Object Drawing.Point(10, $y); $chkStartSrv.Size = New-Object Drawing.Size(200, 25)
$chkStartSrv.Add_CheckedChanged({ $global:StartServerAfterInstall = $this.Checked })
$tabSettings.Controls.Add($chkStartSrv)

$tabControl.Controls.Add($tabSettings)

# Tab 3: Install Progress
$tabInstall = New-Object System.Windows.Forms.TabPage
$tabInstall.Text = "Install"
$progressBox = New-Object System.Windows.Forms.RichTextBox
$progressBox.Size = New-Object System.Drawing.Size(590, 280)
$progressBox.Location = New-Object System.Drawing.Point(10, 10)
$progressBox.ReadOnly = $true
$progressBox.BackColor = [System.Drawing.Color]::Black
$progressBox.ForeColor = [System.Drawing.Color]::Lime
$progressBox.Font = New-Object Drawing.Font("Consolas", 9)
$tabInstall.Controls.Add($progressBox)
$tabControl.Controls.Add($tabInstall)

$form.Controls.Add($tabControl)

# Bottom buttons
$btnInstall = New-Object Windows.Forms.Button
$btnInstall.Text = "Install"
$btnInstall.Size = New-Object Drawing.Size(100, 35)
$btnInstall.Location = New-Object Drawing.Point(530, 440)
$btnInstall.BackColor = [Drawing.Color]::FromArgb(25, 135, 84)
$btnInstall.ForeColor = [Drawing.Color]::White

$btnCancel = New-Object Windows.Forms.Button
$btnCancel.Text = "Cancel"
$btnCancel.Size = New-Object Drawing.Size(100, 35)
$btnCancel.Location = New-Object Drawing.Point(420, 440)
$btnCancel.Add_Click({ $form.Close() })

$form.Controls.AddRange(@($btnInstall, $btnCancel))

# ============================================================
# INSTALL LOGIC
# ============================================================
function Write-Log {
    param([string]$Message, [string]$Color = "White")
    $progressBox.SelectionColor = switch ($Color) { "Green" { [Drawing.Color]::Lime }; "Red" { [Drawing.Color]::Red }; "Yellow" { [Drawing.Color]::Yellow }; "Cyan" { [Drawing.Color]::Cyan }; default { [Drawing.Color]::White } }
    $progressBox.AppendText("$Message`r`n")
    $progressBox.ScrollToCaret()
    [System.Windows.Forms.Application]::DoEvents()
}

function Find-Psql {
    $paths = @(
        "C:\Program Files\PostgreSQL\18\bin\psql.exe",
        "C:\Program Files\PostgreSQL\17\bin\psql.exe",
        "C:\Program Files\PostgreSQL\16\bin\psql.exe",
        "C:\Program Files\PostgreSQL\15\bin\psql.exe",
        "C:\Program Files\PostgreSQL\14\bin\psql.exe"
    )
    foreach ($p in $paths) { if (Test-Path $p) { return $p } }
    return (Get-Command "psql" -ErrorAction SilentlyContinue).Source
}

function Find-Php {
    $paths = @("$global:InstallPath\php\php.exe", "C:\xampp\php\php.exe", "C:\php\php.exe")
    foreach ($p in $paths) { if (Test-Path $p) { return $p } }
    return (Get-Command "php" -ErrorAction SilentlyContinue).Source
}

function Create-Shortcut {
    param([string]$TargetPath, [string]$ShortcutPath, [string]$Arguments, [string]$Description, [string]$IconPath)
    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($ShortcutPath)
    $shortcut.TargetPath = $TargetPath
    if ($Arguments) { $shortcut.Arguments = $Arguments }
    if ($Description) { $shortcut.Description = $Description }
    if ($IconPath -and (Test-Path $IconPath)) { $shortcut.IconLocation = $IconPath }
    $shortcut.WorkingDirectory = $global:InstallPath
    $shortcut.Save()
}

function Install-PlexiQ {
    $tabControl.SelectedIndex = 2
    $btnInstall.Enabled = $false
    $btnCancel.Enabled = $false

    try {
        # Step 1: Create directories
        Write-Log "========================================" Cyan
        Write-Log "  PlexiQ LIMS Server Installation" Cyan
        Write-Log "========================================" Cyan
        Write-Log ""

        Write-Log "[1/7] Creating directories..." Yellow
        $dirs = @("public", "app", "config", "database", "resources", "routes", "storage\logs", "storage\sessions", "docs")
        foreach ($d in $dirs) {
            $fullPath = "$global:InstallPath\$d"
            New-Item -Path $fullPath -ItemType Directory -Force -ErrorAction SilentlyContinue | Out-Null
            Write-Log "  Created: $fullPath" Green
        }

        # Step 2: Copy application files
        Write-Log "[2/7] Copying application files..." Yellow
        $copyDirs = @("public", "app", "config", "database", "resources", "routes", "docs")
        $totalFiles = 0
        foreach ($d in $copyDirs) {
            $src = "$AppSource\$d"
            $dst = "$global:InstallPath\$d"
            if (Test-Path $src) {
                Copy-Item -Path "$src\*" -Destination $dst -Recurse -Force -ErrorAction SilentlyContinue
                $count = (Get-ChildItem -Path $dst -Recurse -File -ErrorAction SilentlyContinue).Count
                $totalFiles += $count
                Write-Log "  Copied $count files: $d" Green
            } else {
                Write-Log "  [WARN] Source not found: $src" Yellow
            }
        }

        # Copy root files
        $rootFiles = @(".env", "composer.json", "composer.lock")
        foreach ($f in $rootFiles) {
            if (Test-Path "$AppSource\$f") {
                Copy-Item "$AppSource\$f" "$global:InstallPath\" -Force
                Write-Log "  Copied: $f" Green
            }
        }
        Write-Log "  Total: $totalFiles application files copied" Green

        # Step 3: Create .env
        Write-Log "[3/7] Creating configuration..." Yellow
        $envContent = @"
# PlexiQ LIMS - Environment Configuration
DB_HOST=$global:DbHost
DB_PORT=$global:DbPort
DB_DATABASE=$global:DbName
DB_USERNAME=$global:DbUser
DB_PASSWORD=$global:DbPass
SERVER_PORT=$global:InstallPort
APP_URL=http://localhost:$global:InstallPort
APP_ENV=production
APP_DEBUG=false
SESSION_DRIVER=file
"@
        Set-Content -Path "$global:InstallPath\.env" -Value $envContent

        $configContent = @"
; PlexiQ LIMS Server Configuration
PORT=$global:InstallPort
PHP_PATH=$(Find-Php)
"@
        Set-Content -Path "$global:InstallPath\config.ini" -Value $configContent
        Write-Log "  Configuration saved" Green

        # Step 4: Check prerequisites
        Write-Log "[4/7] Checking prerequisites..." Yellow
        $php = Find-Php
        if ($php) {
            $v = & $php -r "echo PHP_VERSION;" 2>$null
            Write-Log "  PHP: $php (v$v)" Green
        } else {
            Write-Log "  [WARN] PHP not found. Install PHP 8.0+ manually." Yellow
        }

        $psql = Find-Psql
        if ($psql) {
            $v = & $psql --version 2>&1
            Write-Log "  PostgreSQL: $psql ($v)" Green
        } else {
            Write-Log "  [WARN] PostgreSQL not found. Install PostgreSQL 14+ manually." Yellow
        }

        # Step 5: Database setup
        Write-Log "[5/7] Setting up database..." Yellow
        if ($psql) {
            $env:PGPASSWORD = $global:DbPass
            $result = & $psql -h $global:DbHost -p $global:DbPort -U $global:DbUser -c "SELECT 1" -q 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Log "  Database connection OK" Green

                # Create database
                $dbExists = & $psql -h $global:DbHost -p $global:DbPort -U $global:DbUser -lqt 2>&1 | Select-String "^ $global:DbName "
                if (-not $dbExists) {
                    & $psql -h $global:DbHost -p $global:DbPort -U $global:DbUser -c "CREATE DATABASE $global:DbName;" -q 2>&1 | Out-Null
                    Write-Log "  Database '$global:DbName' created" Green
                } else {
                    Write-Log "  Database '$global:DbName' already exists" Yellow
                }

                # Import schema
                if (Test-Path "$global:InstallPath\database\schema.sql") {
                    & $psql -h $global:DbHost -p $global:DbPort -U $global:DbUser -d $global:DbName -f "$global:InstallPath\database\schema.sql" -q 2>&1 | Out-Null
                    Write-Log "  Schema imported" Green
                }

                # Import migrations
                $migrationsDir = "$global:InstallPath\database\migrations"
                if (Test-Path $migrationsDir) {
                    $migrations = Get-ChildItem $migrationsDir -Filter "*.sql" | Sort-Object Name
                    foreach ($m in $migrations) {
                        & $psql -h $global:DbHost -p $global:DbPort -U $global:DbUser -d $global:DbName -f $m.FullName -q 2>&1 | Out-Null
                        Write-Log "  Migration: $($m.Name)" Green
                    }
                }
            } else {
                Write-Log "  [FAIL] Cannot connect to database: $result" Red
            }
            Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
        }

        # Step 6: Create shortcuts
        Write-Log "[6/7] Creating shortcuts..." Yellow
        $iconPath = if (Test-Path "$global:InstallPath\assets\icon.ico") { "$global:InstallPath\assets\icon.ico" } else { "" }
        $startBat = "$global:InstallPath\start-server.bat"

        if ($global:CreateDesktopShortcut) {
            $desktop = [Environment]::GetFolderPath("Desktop")
            Create-Shortcut -TargetPath $startBat -ShortcutPath "$desktop\PlexiQ LIMS Server.lnk" -Description "PlexiQ LIMS Server" -IconPath $iconPath
            Write-Log "  Desktop shortcut created" Green
        }

        if ($global:CreateStartMenuShortcut) {
            $startMenu = [Environment]::GetFolderPath("Programs")
            $folder = "$startMenu\PlexiQ LIMS"
            New-Item -Path $folder -ItemType Directory -Force -ErrorAction SilentlyContinue | Out-Null
            Create-Shortcut -TargetPath $startBat -ShortcutPath "$folder\Start Server.lnk" -Description "Start PlexiQ LIMS Server" -IconPath $iconPath
            Create-Shortcut -TargetPath "$global:InstallPath\stop-server.bat" -ShortcutPath "$folder\Stop Server.lnk" -Description "Stop PlexiQ LIMS Server" -IconPath $iconPath
            Create-Shortcut -TargetPath "http://localhost:$global:InstallPort" -ShortcutPath "$folder\Dashboard.lnk" -Description "Open PlexiQ LIMS Dashboard"
            Create-Shortcut -TargetPath "$global:InstallPath\validate-install.bat" -ShortcutPath "$folder\Validate Installation.lnk" -Description "Validate PlexiQ LIMS Installation" -IconPath $iconPath
            Write-Log "  Start Menu shortcuts created" Green
        }

        # Step 7: Start server
        Write-Log "[7/7] Finalizing..." Yellow
        if ($global:StartServerAfterInstall -and $php) {
            Write-Log "  Starting server on port $global:InstallPort..." Cyan
            $startInfo = New-Object System.Diagnostics.ProcessStartInfo
            $startInfo.FileName = "cmd.exe"
            $startInfo.Arguments = "/c start `"PlexiQ LIMS Server (Port $global:InstallPort)`" `"$php`" -S 0.0.0.0:$global:InstallPort -t `"$global:InstallPath\public`""
            $startInfo.WorkingDirectory = $global:InstallPath
            $startInfo.UseShellExecute = $true
            [System.Diagnostics.Process]::Start($startInfo) | Out-Null
            Write-Log "  Server starting on http://localhost:$global:InstallPort" Green
        }

        # Complete
        Write-Log ""
        Write-Log "========================================" Green
        Write-Log "  Installation Complete!" Green
        Write-Log "========================================" Green
        Write-Log ""
        Write-Log "  Installed to: $global:InstallPath" White
        Write-Log "  Server URL:   http://localhost:$global:InstallPort" White
        Write-Log "  Admin login:  admin / admin@123" White
        Write-Log "  Customer:     customer / admin@123" White
        Write-Log ""

        $btnInstall.Text = "Close"
        $btnInstall.Enabled = $true
        $btnInstall.Add_Click({ $form.Close() })
        $btnCancel.Enabled = $true

    } catch {
        Write-Log "[FATAL] Installation failed: $_" Red
        $btnInstall.Text = "Retry"
        $btnInstall.Enabled = $true
        $btnCancel.Enabled = $true
    }
}

$btnInstall.Add_Click({ Install-PlexiQ })

# Show the form
$form.ShowDialog() | Out-Null
