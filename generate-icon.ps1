param(
    [int]$Size = 256,
    [string]$OutputDir = ""
)

Add-Type -AssemblyName System.Drawing

if (-not $OutputDir) {
    $OutputDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}

Write-Host "Generating PlexiQ LIMS icon..." -ForegroundColor Cyan

# ============================================================
# Canvas setup
# ============================================================
$bmp = New-Object System.Drawing.Bitmap($Size, $Size)
$bmp.SetResolution(96, 96)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'HighQuality'
$g.InterpolationMode = 'HighQualityBicubic'
$g.PixelOffsetMode = 'HighQuality'
$g.TextRenderingHint = 'AntiAliasGridFit'
$g.Clear([System.Drawing.Color]::Transparent)

# Scale factor
$s = $Size / 256.0

# ============================================================
# Colors
# ============================================================
$darkBlue = [System.Drawing.Color]::FromArgb(15, 25, 35)
$brand = [System.Drawing.Color]::FromArgb(0, 212, 170)
$primary = [System.Drawing.Color]::FromArgb(43, 123, 228)
$purple = [System.Drawing.Color]::FromArgb(118, 75, 162)
$white = [System.Drawing.Color]::FromArgb(255, 255, 255)
$lightGray = [System.Drawing.Color]::FromArgb(200, 210, 220)

# ============================================================
# Background rounded hexagon
# ============================================================
$bgPath = New-Object System.Drawing.Drawing2D.GraphicsPath
$cx = 128 * $s; $cy = 128 * $s
$r = 118 * $s
for ($i = 0; $i -lt 6; $i++) {
    $angle = -90 + $i * 60
    $rad = $angle * [Math]::PI / 180
    $px = $cx + $r * [Math]::Cos($rad)
    $py = $cy + $r * [Math]::Sin($rad)
    if ($i -eq 0) {
        $bgPath.StartFigure()
    }
    if ($i -eq 0) {
        $bgPath.AddLine($px, $py, $px, $py)
    } else {
        $bgPath.AddLine($px, $py, $px, $py)
    }
}
$bgPath.CloseFigure()

$gradBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    (New-Object System.Drawing.Point(0, 0)),
    (New-Object System.Drawing.Point($Size, $Size)),
    $primary, $purple
)
$g.FillPath($gradBrush, $bgPath)

# Subtle inner glow
$innerR = 108 * $s
$innerPath = New-Object System.Drawing.Drawing2D.GraphicsPath
for ($i = 0; $i -lt 6; $i++) {
    $angle = -90 + $i * 60
    $rad = $angle * [Math]::PI / 180
    $px = $cx + $innerR * [Math]::Cos($rad)
    $py = $cy + $innerR * [Math]::Sin($rad)
    if ($i -eq 0) {
        $innerPath.StartFigure()
        $innerPath.AddLine($px, $py, $px, $py)
    } else {
        $innerPath.AddLine($px, $py, $px, $py)
    }
}
$innerPath.CloseFigure()
$glowPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(60, 255, 255, 255), 4 * $s)
$g.DrawPath($glowPen, $innerPath)

# ============================================================
# Chemical flask / beaker icon
# ============================================================
$p = New-Object System.Drawing.Pen($white, (3 * $s))
$p.StartCap = 'Round'
$p.EndCap = 'Round'

# Flask body (rounded bottom with neck)
$flaskX = $cx - 40 * $s
$flaskY = $cy - 50 * $s
$flaskW = 80 * $s
$flaskH = 90 * $s

# Neck - two vertical lines
$neckTop = $cy - 70 * $s
$neckBottom = $cy - 50 * $s
$neckLeft = $cx - 15 * $s
$neckRight = $cx + 15 * $s

$g.DrawLine($p, $neckLeft, $neckTop, $neckLeft, $neckBottom)
$g.DrawLine($p, $neckRight, $neckTop, $neckRight, $neckBottom)

# Flask body - trapezoid shape getting wider at bottom
$bodyPath = New-Object System.Drawing.Drawing2D.GraphicsPath
$bodyPath.AddLine($neckLeft, $neckBottom, $cx - 50 * $s, $cy + 25 * $s)
$bodyPath.AddArc($cx - 50 * $s, $cy + 10 * $s, 100 * $s, 35 * $s, 0, 180)
$bodyPath.AddLine($cx + 50 * $s, $cy + 25 * $s, $neckRight, $neckBottom)
$g.DrawPath($p, $bodyPath)

# ============================================================
# Liquid inside flask (gradient fill)
# ============================================================
$liquidPath = New-Object System.Drawing.Drawing2D.GraphicsPath
$liquidPath.AddLine($cx - 45 * $s, $cy + 15 * $s, $cx + 45 * $s, $cy + 15 * $s)

$bottomArc = New-Object System.Drawing.RectangleF(
    ($cx - 46 * $s), ($cy + 10 * $s),
    (92 * $s), (28 * $s)
)
$liquidPath.AddArc($bottomArc, 0, 180)
$liquidPath.CloseFigure()

$liquidGrad = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    (New-Object System.Drawing.PointF(0, $cy * $s)),
    (New-Object System.Drawing.PointF(0, ($cy + 35) * $s)),
    [System.Drawing.Color]::FromArgb(180, 0, 212, 170),
    [System.Drawing.Color]::FromArgb(220, 0, 180, 150)
)
$g.FillPath($liquidGrad, $liquidPath)

# Liquid surface line
$surfacePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(200, 0, 240, 200), (1.5 * $s))
$g.DrawLine($surfacePen, $cx - 45 * $s, $cy + 15 * $s, $cx + 45 * $s, $cy + 15 * $s)

# ============================================================
# Bubbles in liquid
# ============================================================
$bubblePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(100, 255, 255, 255), (1 * $s))
$b1x = $cx - 25 * $s; $b1y = $cy + 22 * $s; $b1r = 6 * $s
$b2x = $cx + 15 * $s; $b2y = $cy + 28 * $s; $b2r = 4 * $s
$b3x = $cx - 8 * $s; $b3y = $cy + 18 * $s; $b3r = 3 * $s
$b4x = $cx + 30 * $s; $b4y = $cy + 20 * $s; $b4r = 5 * $s
$g.DrawEllipse($bubblePen, $b1x - $b1r/2, $b1y - $b1r/2, $b1r, $b1r)
$g.DrawEllipse($bubblePen, $b2x - $b2r/2, $b2y - $b2r/2, $b2r, $b2r)
$g.DrawEllipse($bubblePen, $b3x - $b3r/2, $b3y - $b3r/2, $b3r, $b3r)
$g.DrawEllipse($bubblePen, $b4x - $b4r/2, $b4y - $b4r/2, $b4r, $b4r)

# ============================================================
# "P" letter on flask
# ============================================================
$letterFont = New-Object System.Drawing.Font("Segoe UI", (32 * $s), [System.Drawing.FontStyle]::Bold)
$letterBrush = New-Object System.Drawing.SolidBrush($white)
$letterFormat = New-Object System.Drawing.StringFormat
$letterFormat.Alignment = 'Center'
$letterFormat.LineAlignment = 'Center'
$g.DrawString("P", $letterFont, $letterBrush, $cx, $cy - 8 * $s, $letterFormat)

# ============================================================
# PlexiQ dot accent
# ============================================================
$dotBrush = New-Object System.Drawing.SolidBrush($brand)
$g.FillEllipse($dotBrush, $cx + 22 * $s, $cy - 85 * $s, 8 * $s, 8 * $s)

# ============================================================
# Save as PNG
# ============================================================
$pngPath = Join-Path $OutputDir "public\assets\images\plexiq-icon.png"
$pngDir = Split-Path $pngPath -Parent
if (-not (Test-Path $pngDir)) { New-Item -ItemType Directory -Path $pngDir -Force | Out-Null }
$bmp.Save($pngPath, [System.Drawing.Imaging.ImageFormat]::Png)
Write-Host "  PNG: $pngPath" -ForegroundColor Green

# ============================================================
# Save as ICO (multi-resolution)
# ============================================================
$icoPath = Join-Path $OutputDir "public\favicon.ico"
$icoSizes = @(16, 32, 48, 64, 128, 256)

$icoStream = New-Object System.IO.FileStream($icoPath, [System.IO.FileMode]::Create)
$icoWriter = New-Object System.IO.BinaryWriter($icoStream)

$icoWriter.Write([UInt16]0)
$icoWriter.Write([UInt16]1)
$icoWriter.Write([UInt16]$icoSizes.Length)

$imageData = @{}
$totalOffset = 6 + ($icoSizes.Length * 16)

foreach ($size in $icoSizes) {
    $resized = New-Object System.Drawing.Bitmap($bmp, $size, $size)
    $ms = New-Object System.IO.MemoryStream
    $resized.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $data = $ms.ToArray()
    $imageData[$size] = @{ Data = $data }
    $ms.Dispose()
    $resized.Dispose()
}

$icoWriter.BaseStream.Position = 6
foreach ($size in $icoSizes) {
    $icoWriter.Write([Byte]($size -band 0xFF))
    $icoWriter.Write([Byte]($size -band 0xFF))
    $icoWriter.Write([Byte]0)
    $icoWriter.Write([Byte]0)
    $icoWriter.Write([UInt16]1)
    $icoWriter.Write([UInt16]32)
    $icoWriter.Write([UInt32]$imageData[$size].Data.Length)
    $icoWriter.Write([UInt32]$totalOffset)
    $totalOffset += $imageData[$size].Data.Length
}

foreach ($size in $icoSizes) {
    $icoWriter.Write($imageData[$size].Data)
}

$icoWriter.Flush()
$icoWriter.Dispose()
$icoStream.Dispose()

Write-Host "  ICO: $icoPath ($($icoSizes.Length) resolutions)" -ForegroundColor Green

# ============================================================
# Copy ICO to client-installer assets
# ============================================================
$clientIcoPath = Join-Path $OutputDir "client-installer\assets\icon.ico"
$clientIcoDir = Split-Path $clientIcoPath -Parent
if (-not (Test-Path $clientIcoDir)) { New-Item -ItemType Directory -Path $clientIcoDir -Force | Out-Null }
Copy-Item $icoPath $clientIcoPath -Force
Write-Host "  ICO: $clientIcoPath (client installer)" -ForegroundColor Green

# ============================================================
# Copy logo BMP to client-installer assets
# ============================================================
$logoBmp = New-Object System.Drawing.Bitmap(164, 314)
$lg = [System.Drawing.Graphics]::FromImage($logoBmp)
$lg.SmoothingMode = 'HighQuality'
$lg.Clear([System.Drawing.Color]::FromArgb(15, 25, 35))
$brandBar = New-Object System.Drawing.SolidBrush($brand)
$lg.FillRectangle($brandBar, 0, 0, 164, 4)

$iconSmall = New-Object System.Drawing.Bitmap($bmp, 64, 64)
$lg.DrawImage($iconSmall, 50, 30, 64, 64)

$titleFont = New-Object System.Drawing.Font("Segoe UI", 16, [System.Drawing.FontStyle]::Bold)
$subFont = New-Object System.Drawing.Font("Segoe UI", 9, [System.Drawing.FontStyle]::Regular)
$lg.DrawString("PlexiQ", $titleFont, [System.Drawing.SystemBrushes]::Window, 82 - 25, 110)
$brandBrush = New-Object System.Drawing.SolidBrush($brand)
$lg.DrawString("LIMS", $subFont, $brandBrush, 82 - 15, 136)

$bmpLogoPath = Join-Path $OutputDir "client-installer\assets\logo.bmp"
$logoBmp.Save($bmpLogoPath, [System.Drawing.Imaging.ImageFormat]::Bmp)
$lg.Dispose()
$logoBmp.Dispose()
$iconSmall.Dispose()
Write-Host "  BMP: $bmpLogoPath (installer wizard)" -ForegroundColor Green

# ============================================================
# Dispose
# ============================================================
$g.Dispose()
$bmp.Dispose()
$letterFormat.Dispose()

Write-Host ""
Write-Host "Icon generation complete!" -ForegroundColor Cyan
