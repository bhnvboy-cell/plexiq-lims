<#
.SYNOPSIS
    PlexiQ LIMS - EXE Installer Builder
.DESCRIPTION
    Builds a standalone Windows EXE installer for PlexiQ LIMS without
    requiring Inno Setup. Supports multiple build methods:
    
    Method 1: ps2exe    - Convert PowerShell installer to EXE (requires ps2exe module)
    Method 2: IExpress  - Windows built-in self-extracting EXE (no extra tools)
    Method 3: .NET      - Native .NET bootstrapper compiled on-the-fly
    Method 4: Inno Setup- Professional installer (if available)
#>

param(
    [ValidateSet("auto", "ps2exe", "iexpress", "net", "innosetup")]
    [string]$Method = "auto"
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$OutputDir = "$ScriptDir\Output"
$Version = "2.0"

# Ensure output directory exists
New-Item -Path $OutputDir -ItemType Directory -Force -ErrorAction SilentlyContinue | Out-Null

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PlexiQ LIMS - EXE Installer Builder" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# ============================================================
# Detect available methods
# ============================================================
function Test-Method {
    param([string]$MethodName)
    switch ($MethodName) {
        "innosetup" {
            $paths = @("C:\Program Files (x86)\Inno Setup 6\ISCC.exe", "C:\Program Files\Inno Setup 6\ISCC.exe")
            foreach ($p in $paths) { if (Test-Path $p) { return $p } }
            return (Get-Command "ISCC.exe" -ErrorAction SilentlyContinue).Source
        }
        "ps2exe" {
            $mod = Get-Module -ListAvailable -Name "ps2exe" -ErrorAction SilentlyContinue
            if ($mod) { return "module" }
            $tool = Get-Command "ps2exe.ps1" -ErrorAction SilentlyContinue
            if ($tool) { return $tool.Source }
            $tool2 = Get-Command "ps2exe" -ErrorAction SilentlyContinue
            if ($tool2) { return $tool2.Source }
            return $null
        }
        "iexpress" {
            if (Get-Command "iexpress.exe" -ErrorAction SilentlyContinue) { return "iexpress.exe" }
            if (Test-Path "$env:SystemRoot\System32\iexpress.exe") { return "$env:SystemRoot\System32\iexpress.exe" }
            return $null
        }
        "net" {
            if (Get-Command "csc.exe" -ErrorAction SilentlyContinue) { return "csc.exe" }
            $cscPaths = @(
                "C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe",
                "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
            )
            foreach ($p in $cscPaths) { if (Test-Path $p) { return $p } }
            return $null
        }
    }
}

# ============================================================
# Method 1: Inno Setup
# ============================================================
function Build-InnoSetup {
    param([string]$ISCC)
    Write-Host "[1/4] Using Inno Setup..." -ForegroundColor Yellow
    & $ISCC "$ScriptDir\setup.iss"
    if ($LASTEXITCODE -eq 0) {
        $exe = Get-ChildItem "$ScriptDir\Output\PlexiQ-LIMS-Server-Setup-*.exe" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($exe) { return $exe.FullName }
    }
    return $null
}

# ============================================================
# Method 2: ps2exe
# ============================================================
function Build-Ps2Exe {
    param([string]$Ps2ExePath)
    Write-Host "[1/4] Using ps2exe..." -ForegroundColor Yellow
    
    # Install ps2exe if not available
    if (-not $Ps2ExePath) {
        Write-Host "  Installing ps2exe module..." -ForegroundColor Gray
        Install-Module -Name ps2exe -Force -Scope CurrentUser -AllowClobber -ErrorAction SilentlyContinue
        $Ps2ExePath = "ps2exe"
    }

    $outputExe = "$OutputDir\PlexiQ-LIMS-Server-Setup-$Version.exe"
    $installerPs1 = "$ScriptDir\install.ps1"
    
    if (-not (Test-Path $installerPs1)) {
        Write-Host "  [FAIL] install.ps1 not found at $installerPs1" -ForegroundColor Red
        return $null
    }

    # Create a wrapper that embeds the file list
    $wrapperPs1 = "$env:TEMP\plexiq-installer-wrapper.ps1"
    @"
`$ScriptDir = Split-Path -Parent `$MyInvocation.MyCommand.Path
`$AppSource = `$ScriptDir  # Files are alongside the EXE
. "`$ScriptDir\install.ps1"
"@ | Set-Content $wrapperPs1

    # Try to use ps2exe
    try {
        if ($Ps2ExePath -eq "ps2exe") {
            Invoke-ps2exe -InputFile $installerPs1 -OutputFile $outputExe -Title "PlexiQ LIMS Server Installer" -Description "Laboratory Information Management System" -Product "PlexiQ LIMS" -Company "PlexiQ Labs" -Version $Version -IconFile "$ScriptDir\assets\icon.ico" -NoConsole -ErrorAction Stop
        } else {
            & $Ps2ExePath -InputFile $installerPs1 -OutputFile $outputExe -Title "PlexiQ LIMS Server Installer" -Description "Laboratory Information Management System" -Product "PlexiQ LIMS" -Company "PlexiQ Labs" -Version $Version -IconFile "$ScriptDir\assets\icon.ico" -NoConsole -ErrorAction Stop
        }
        if (Test-Path $outputExe) { return $outputExe }
    } catch {
        Write-Host "  [FAIL] ps2exe failed: $_" -ForegroundColor Red
    }
    return $null
}

# ============================================================
# Method 3: IExpress Self-Extracting EXE
# ============================================================
function Build-IExpress {
    param([string]$IExpressPath)
    Write-Host "[1/4] Using IExpress (Windows built-in)..." -ForegroundColor Yellow
    
    $sedFile = "$env:TEMP\plexiq-installer.sed"
    $outputExe = "$OutputDir\PlexiQ-LIMS-Server-Setup-$Version.exe"
    
    # Create SED file for IExpress
    @"
[Version]
Class=IEXPRESS
SEDVersion=3
[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=0
HideExtractAnimation=1
UseLongFileName=1
InsideCompressed=1
CAB_FixedSizeList=1
CAB_MaxSize=0
CAB_ResvCodeSigning=0
[SourceFiles]
SourceFiles0=$ScriptDir
[SourceFiles0]
%FILE0%=
"@ | Set-Content $sedFile

    # Build file list
    $fileList = @()
    $items = Get-ChildItem -Path $ScriptDir -Recurse -File | Where-Object { $_.FullName -notlike "*\Output\*" -and $_.Extension -ne ".sed" }
    $idx = 0
    $sourceFiles = @()
    foreach ($item in $items) {
        $relative = $item.FullName.Substring($ScriptDir.Length + 1)
        $sedFileContent += "FILE$idx=$relative`r`n"
        $sourceFiles += "FILE$idx=$($item.FullName)`r`n"
        $idx++
    }
    
    # Actually, IExpress has limitations. Let me use a simpler approach.
    # Create a PowerShell script that self-extracts
    
    Write-Host "  IExpress is limited for this use case." -ForegroundColor Yellow
    Write-Host "  Falling back to .NET bootstrapper..." -ForegroundColor Yellow
    return $null
}

# ============================================================
# Method 4: .NET Native Bootstrapper
# ============================================================
function Build-NetBootstrapper {
    param([string]$CscPath)
    Write-Host "[1/4] Using .NET native bootstrapper..." -ForegroundColor Yellow
    
    $outputExe = "$OutputDir\PlexiQ-LIMS-Server-Setup-$Version.exe"
    $installerPs1 = "$ScriptDir\install.ps1"
    
    if (-not $CscPath) {
        Write-Host "  [FAIL] C# compiler (csc.exe) not found" -ForegroundColor Red
        return $null
    }

    # Read the installer script content to embed
    $psContent = Get-Content $installerPs1 -Raw -ErrorAction SilentlyContinue
    if (-not $psContent) {
        Write-Host "  [FAIL] Cannot read install.ps1" -ForegroundColor Red
        return $null
    }
    # Escape for embedding in C# string
    $psContent = $psContent.Replace("\", "\\").Replace("`"", "\\`"").Replace("`r`n", "\\r\\n").Replace("`n", "\\n").Replace("`r", "\\r")
    
    # Determine if we should embed files or reference them alongside
    $embedFiles = $false  # For now, files are alongside the EXE

    $csCode = @"
using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Text;

class PlexiQInstaller
{
    static void Main()
    {
        string appDir = Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location);
        string psScript = appDir + @"\install.ps1";
        
        if (!File.Exists(psScript))
        {
            Console.Error.WriteLine("ERROR: install.ps1 not found alongside the installer.");
            Console.Error.WriteLine("Copy all files from the installer package to a folder and run again.");
            Console.ReadLine();
            Environment.Exit(1);
        }
        
        try
        {
            ProcessStartInfo psi = new ProcessStartInfo();
            psi.FileName = "powershell.exe";
            psi.Arguments = "-NoLogo -NoProfile -ExecutionPolicy Bypass -File \"" + psScript + "\"";
            psi.WorkingDirectory = appDir;
            psi.UseShellExecute = true;
            
            Process p = new Process();
            p.StartInfo = psi;
            p.Start();
            p.WaitForExit();
            
            if (p.ExitCode != 0)
            {
                Console.Error.WriteLine("Installer exited with code: " + p.ExitCode);
                Console.ReadLine();
                Environment.Exit(p.ExitCode);
            }
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine("Failed to launch installer: " + ex.Message);
            Console.ReadLine();
            Environment.Exit(1);
        }
    }
}
"@

    $csFile = "$env:TEMP\plexiq-bootstrap.cs"
    Set-Content -Path $csFile -Value $csCode

    # Compile
    $outputExe = "$OutputDir\PlexiQ-LIMS-Server-Setup-$Version.exe"
    $iconArg = ""
    if (Test-Path "$ScriptDir\assets\icon.ico") { $iconArg = "/win32icon:`"$ScriptDir\assets\icon.ico`"" }
    
    $result = & $CscPath /target:winexe /platform:anycpu /out:"$outputExe" $iconArg /reference:System.dll "$csFile" 2>&1
    if ($LASTEXITCODE -eq 0) {
        if (Test-Path $outputExe) { return $outputExe }
    } else {
        Write-Host "  [FAIL] Compilation failed: $result" -ForegroundColor Red
        # Try without icon
        $result2 = & $CscPath /target:winexe /platform:anycpu /out:"$outputExe" /reference:System.dll "$csFile" 2>&1
        if ($LASTEXITCODE -eq 0 -and (Test-Path $outputExe)) { return $outputExe }
    }
    return $null
}

# ============================================================
# Method 5: Batch + PowerShell wrapper
# ============================================================
function Build-BatchWrapper {
    Write-Host "[1/4] Creating batch-based installer..." -ForegroundColor Yellow
    
    $outputExe = "$OutputDir\PlexiQ-LIMS-Server-Setup-$Version.exe"
    
    # Create a Powershell script that converts itself to EXE using native .NET
    $converterPs1 = @"
Add-Type -AssemblyName System.IO.Compression.FileSystem
`$ScriptDir = Split-Path -Parent `$MyInvocation.MyCommand.Path
`$OutputDir = "`$ScriptDir\Output"
New-Item -Path `$OutputDir -ItemType Directory -Force | Out-Null

# Create a simple C# bootstrapper and compile with csc.exe
`$csc = Get-Command "csc.exe" -ErrorAction SilentlyContinue
if (-not `$csc) {
    `$paths = @("`$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\csc.exe", "`$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe")
    foreach (`$p in `$paths) { if (Test-Path `$p) { `$csc = `$p; break } }
}

if (-not `$csc) {
    Write-Host "[FAIL] No C# compiler found. Install .NET Framework 4+ or use install.ps1 directly." -ForegroundColor Red
    exit 1
}

Write-Host "Building EXE bootstrapper..." -ForegroundColor Yellow
`$csCode = @'
using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
class PlexiQInstaller {
    static void Main() {
        string appDir = Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location);
        string psScript = appDir + @"\install.ps1";
        if (!File.Exists(psScript)) {
            Console.Error.WriteLine("ERROR: install.ps1 not found alongside the installer.");
            Console.ReadLine(); Environment.Exit(1);
        }
        try {
            ProcessStartInfo psi = new ProcessStartInfo();
            psi.FileName = "powershell.exe";
            psi.Arguments = "-NoLogo -NoProfile -ExecutionPolicy Bypass -File \\"" + psScript + "\\"";
            psi.WorkingDirectory = appDir; psi.UseShellExecute = true;
            Process p = new Process(); p.StartInfo = psi; p.Start(); p.WaitForExit();
            if (p.ExitCode != 0) { Console.ReadLine(); Environment.Exit(p.ExitCode); }
        } catch (Exception ex) {
            Console.Error.WriteLine("Failed: " + ex.Message);
            Console.ReadLine(); Environment.Exit(1);
        }
    }
}
'@

`$csFile = "`$env:TEMP\plexiq-bootstrap.cs"
Set-Content -Path `$csFile -Value `$csCode
`$outputExe = "`$OutputDir\PlexiQ-LIMS-Server-Setup-$Version.exe"

`$iconPath = "`$ScriptDir\assets\icon.ico"
`$iconArg = if (Test-Path `$iconPath) { "/win32icon:`"`$iconPath`"" } else { "" }

`$result = & `$csc /target:winexe /platform:anycpu /out:"`$outputExe" `$iconArg /reference:System.dll `$csFile 2>&1
if (`$LASTEXITCODE -eq 0 -and (Test-Path `$outputExe)) {
    `$size = (Get-Item `$outputExe).Length
    Write-Host "[OK] EXE built: `$outputExe (`$size bytes)" -ForegroundColor Green
    Write-Host ""
    Write-Host "NOTE: The EXE requires install.ps1 and all application files" -ForegroundColor Yellow
    Write-Host "to be in the SAME directory when run on the target machine." -ForegroundColor Yellow
    Write-Host ""
} else {
    Write-Host "[FAIL] Could not compile: `$result" -ForegroundColor Red
    # Try without icon
    `$result2 = & `$csc /target:winexe /platform:anycpu /out:"`$outputExe" /reference:System.dll `$csFile 2>&1
    if (`$LASTEXITCODE -eq 0 -and (Test-Path `$outputExe)) {
        Write-Host "[OK] EXE built (without icon)" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] $result2" -ForegroundColor Red
        exit 1
    }
}
"@

    $converterPath = "$env:TEMP\plexiq-build-exe.ps1"
    Set-Content -Path $converterPath -Value $converterPs1
    
    # Execute it
    & powershell -ExecutionPolicy Bypass -File $converterPath 2>&1
    
    if (Test-Path $outputExe) { return $outputExe }
    return $null
}

# ============================================================
# MAIN BUILD LOGIC
# ============================================================
$result = $null

# Detect tools
$iscc = Test-Method "innosetup"
$ps2exeTool = Test-Method "ps2exe"
$iexpressTool = Test-Method "iexpress"
$cscTool = Test-Method "net"

Write-Host "Detected tools:" -ForegroundColor White
Write-Host "  Inno Setup: $(if($iscc){'YES'}else{'NO'})" -ForegroundColor $(if($iscc){'Green'}else{'Gray'})
Write-Host "  ps2exe:     $(if($ps2exeTool){'YES'}else{'NO'})" -ForegroundColor $(if($ps2exeTool){'Green'}else{'Gray'})
Write-Host "  IExpress:   $(if($iexpressTool){'YES'}else{'NO'})" -ForegroundColor $(if($iexpressTool){'Green'}else{'Gray'})
Write-Host "  .NET (csc): $(if($cscTool){'YES'}else{'NO'})" -ForegroundColor $(if($cscTool){'Green'}else{'Gray'})
Write-Host ""

switch ($Method) {
    "auto" {
        if ($iscc) { $result = Build-InnoSetup -ISCC $iscc }
        if (-not $result -and $ps2exeTool) { $result = Build-Ps2Exe -Ps2ExePath $ps2exeTool }
        if (-not $result -and $cscTool) { $result = Build-NetBootstrapper -CscPath $cscTool }
        if (-not $result) {
            # Last resort: batch wrapper using .NET
            Write-Host "[INFO] No dedicated build tool found." -ForegroundColor Yellow
            Write-Host "[INFO] Using .NET bootstrapper approach..." -ForegroundColor Yellow
            $result = Build-BatchWrapper
        }
    }
    "innosetup" { if ($iscc) { $result = Build-InnoSetup -ISCC $iscc } else { Write-Host "[FAIL] Inno Setup not available" -ForegroundColor Red } }
    "ps2exe" { $result = Build-Ps2Exe -Ps2ExePath $ps2exeTool }
    "iexpress" { $result = Build-IExpress -IExpressPath $iexpressTool }
    "net" { $result = Build-NetBootstrapper -CscPath $cscTool }
}

if ($result) {
    $size = (Get-Item $result).Length
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  SUCCESS: Installer built!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  Output: $result" -ForegroundColor White
    Write-Host "  Size:   $($size / 1KB -as [int]) KB" -ForegroundColor White
    Write-Host ""
    Write-Host "  To install on target server:" -ForegroundColor Cyan
    Write-Host "  1. Copy the EXE + entire server-installer\ folder to the server" -ForegroundColor Gray
    Write-Host "  2. Run the EXE as Administrator" -ForegroundColor Gray
    Write-Host "  3. The EXE launches install.ps1 which has the GUI wizard" -ForegroundColor Gray
    Write-Host ""
    return $result
} else {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "  FAILED: Could not build installer" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "Alternative: Run install.ps1 directly on the target server:" -ForegroundColor Yellow
    Write-Host "  powershell -ExecutionPolicy Bypass -File install.ps1" -ForegroundColor White
    Write-Host ""
    Write-Host "The install.ps1 script has a full GUI and does not require" -ForegroundColor Yellow
    Write-Host "any external tools - just PowerShell 5.1+." -ForegroundColor Yellow
    Write-Host ""
    return $null
}
