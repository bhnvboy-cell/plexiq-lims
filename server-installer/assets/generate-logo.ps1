<#
.SYNOPSIS
    PlexiQ LIMS - Installer Asset Generator
.DESCRIPTION
    Generates BMP logo and ICO icon for Inno Setup installer
#>

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$OutputDir = $ScriptDir

# ============================================================
# Generate 48x48 BMP for Wizard logo
# ============================================================
Write-Host "Generating logo BMP (48x48)..." -ForegroundColor Gray

Add-Type -AssemblyName System.Drawing

# Create a bitmap
$bmp = New-Object System.Drawing.Bitmap(48, 48)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'HighQuality'

# Dark blue background
$bgBrush = [System.Drawing.SolidBrush][System.Drawing.Color]::FromArgb(255, 13, 110, 253)
$g.FillRectangle($bgBrush, 0, 0, 48, 48)

# Draw a simple hexagon/flask icon
$pen = [System.Drawing.Pen][System.Drawing.Color]::FromArgb(255, 255, 255, 255)
$pen.Width = 2

# Hexagon shape
$points = @(
    [System.Drawing.Point]::new(24, 6)   # top
    [System.Drawing.Point]::new(40, 14)  # top-right
    [System.Drawing.Point]::new(40, 34)  # bottom-right
    [System.Drawing.Point]::new(24, 42)  # bottom
    [System.Drawing.Point]::new(8, 34)   # bottom-left
    [System.Drawing.Point]::new(8, 14)   # top-left
)
$g.DrawPolygon($pen, $points)

# Chemical flask lines
$g.DrawLine($pen, 24, 24, 24, 38)
$g.DrawLine($pen, 18, 38, 30, 38)
$g.DrawEllipse($pen, 21, 20, 6, 6)

$g.Dispose()
$bmp.Save("$OutputDir\logo.bmp", [System.Drawing.Imaging.ImageFormat]::Bmp)
$bmp.Dispose()

Write-Host "  [OK] logo.bmp generated" -ForegroundColor Green

# ============================================================
# Generate ICO from existing favicon or create one
# ============================================================
Write-Host "Generating icon.ico..." -ForegroundColor Gray

$srcIcon = "$OutputDir\..\..\public\favicon.ico"
if (Test-Path $srcIcon) {
    # Use existing favicon
    Copy-Item $srcIcon "$OutputDir\icon.ico" -Force
    Write-Host "  [OK] icon.ico copied from favicon.ico" -ForegroundColor Green
} else {
    # Create ICO programmatically
    $icoBmp = New-Object System.Drawing.Bitmap(32, 32)
    $g2 = [System.Drawing.Graphics]::FromImage($icoBmp)
    $g2.SmoothingMode = 'HighQuality'

    $bgBrush2 = [System.Drawing.SolidBrush][System.Drawing.Color]::FromArgb(255, 13, 110, 253)
    $g2.FillRectangle($bgBrush2, 0, 0, 32, 32)

    $pen2 = [System.Drawing.Pen][System.Drawing.Color]::FromArgb(255, 255, 255, 255)
    $pen2.Width = 1.5

    # Simplified hexagon
    $pts = @(
        [System.Drawing.Point]::new(16, 4)
        [System.Drawing.Point]::new(27, 10)
        [System.Drawing.Point]::new(27, 22)
        [System.Drawing.Point]::new(16, 28)
        [System.Drawing.Point]::new(5, 22)
        [System.Drawing.Point]::new(5, 10)
    )
    $g2.DrawPolygon($pen2, $pts)
    $g2.DrawLine($pen2, 16, 16, 16, 26)
    $g2.DrawLine($pen2, 11, 26, 21, 26)

    $g2.Dispose()

    # Save as PNG first, then use it
    $icoBmp.Save("$OutputDir\temp-icon.png", [System.Drawing.Imaging.ImageFormat]::Png)
    $icoBmp.Dispose()

    # Create ICO from the PNG
    $iconStream = New-Object System.IO.MemoryStream
    $pngBytes = [System.IO.File]::ReadAllBytes("$OutputDir\temp-icon.png")
    $iconStream.Write($pngBytes, 0, $pngBytes.Length)
    $iconStream.Seek(0, [System.IO.SeekOrigin]::Begin)

    $ico = [System.Drawing.Icon]::FromHandle(([System.Drawing.Bitmap]::new($iconStream).GetHicon()))
    $icoStream = New-Object System.IO.FileStream("$OutputDir\icon.ico", [System.IO.FileMode]::Create)
    $ico.Save($icoStream)
    $icoStream.Close()
    $ico.Dispose()
    $iconStream.Dispose()

    Remove-Item "$OutputDir\temp-icon.png" -Force -ErrorAction SilentlyContinue
    Write-Host "  [OK] icon.ico generated" -ForegroundColor Green
}

Write-Host ""
Write-Host "Assets generated successfully." -ForegroundColor Green
