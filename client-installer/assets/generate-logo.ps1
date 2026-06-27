Add-Type -AssemblyName System.Drawing

$bmp = New-Object System.Drawing.Bitmap(164, 314)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'HighQuality'
$g.TextRenderingHint = 'AntiAliasGridFit'

# Background gradient
$brush1 = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    (New-Object System.Drawing.Point(0, 0)),
    (New-Object System.Drawing.Point(0, 314)),
    [System.Drawing.Color]::FromArgb(15, 25, 35),
    [System.Drawing.Color]::FromArgb(26, 45, 61)
)
$g.FillRectangle($brush1, 0, 0, 164, 314)

# Accent bar
$barBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(0, 212, 170))
$g.FillRectangle($barBrush, 0, 0, 164, 4)

# Icon - flask shape
$pen = New-Object System.Drawing.Pen([System.Drawing.Color]::White, 2)
$g.DrawLine($pen, 82, 60, 82, 110)
$g.DrawEllipse($pen, 72, 110, 20, 20)
# Draw stylized flask
$points = @(
    (New-Object System.Drawing.PointF(55, 180)),
    (New-Object System.Drawing.PointF(109, 180)),
    (New-Object System.Drawing.PointF(100, 210)),
    (New-Object System.Drawing.PointF(64, 210))
)
$g.DrawPolygon($pen, $points)
$g.DrawLine($pen, 72, 210, 72, 250)
$g.DrawLine($pen, 92, 210, 92, 250)
$g.DrawLine($pen, 72, 250, 92, 250)

# Title text
$font = New-Object System.Drawing.Font("Segoe UI", 14, [System.Drawing.FontStyle]::Bold)
$brushWhite = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
$g.DrawString("PlexiQ", $font, $brushWhite, 82 - 30, 270)
$fontSmall = New-Object System.Drawing.Font("Segoe UI", 8, [System.Drawing.FontStyle]::Regular)
$brushAccent = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(0, 212, 170))
$g.DrawString("LIMS CLIENT", $fontSmall, $brushAccent, 82 - 25, 292)

$bmp.Save("$PSScriptRoot\logo.bmp", [System.Drawing.Imaging.ImageFormat]::Bmp)
$g.Dispose()
$bmp.Dispose()

Write-Host "Logo BMP generated: $PSScriptRoot\logo.bmp"

# Generate icon (multiresolution .ico)
$icoSizes = @(16, 32, 48, 64)
$iconPath = "$PSScriptRoot\icon.ico"

# Create a simple colored square as icon
$icoBmp = New-Object System.Drawing.Bitmap(64, 64)
$icoG = [System.Drawing.Graphics]::FromImage($icoBmp)
$icoG.SmoothingMode = 'HighQuality'

$bg = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    (New-Object System.Drawing.Point(0, 0)),
    (New-Object System.Drawing.Point(64, 64)),
    [System.Drawing.Color]::FromArgb(43, 123, 228),
    [System.Drawing.Color]::FromArgb(118, 75, 162)
)
$icoG.FillRectangle($bg, 0, 0, 64, 64)
$icoFont = New-Object System.Drawing.Font("Segoe UI", 36, [System.Drawing.FontStyle]::Bold)
$icoG.DrawString("P", $icoFont, [System.Drawing.SystemBrushes]::Window, 12, 8)

$icoStream = New-Object System.IO.FileStream($iconPath, [System.IO.FileMode]::Create)
$icoWriter = New-Object System.IO.BinaryWriter($icoStream)

# ICO header
$icoWriter.Write([UInt16]0)  # reserved
$icoWriter.Write([UInt16]1)  # ICO type
$icoWriter.Write([UInt16]$icoSizes.Length)  # number of images

foreach ($size in $icoSizes) {
    $resized = New-Object System.Drawing.Bitmap($icoBmp, $size, $size)
    $ms = New-Object System.IO.MemoryStream
    $resized.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $data = $ms.ToArray()
    $ms.Dispose()
    $resized.Dispose()

    $icoWriter.Write([Byte]$size)  # width
    $icoWriter.Write([Byte]$size)  # height
    $icoWriter.Write([Byte]0)  # colors
    $icoWriter.Write([Byte]0)  # reserved
    $icoWriter.Write([UInt16]0)  # planes
    $icoWriter.Write([UInt16]8)  # bpp
    $icoWriter.Write([UInt32]$data.Length)  # size
    $icoWriter.Write([UInt32]0)  # offset (will fix later)
}

# Fix offsets and write data
$offset = 6 + ($icoSizes.Length * 16)
$icoWriter.BaseStream.Position = 6
foreach ($size in $icoSizes) {
    $resized = New-Object System.Drawing.Bitmap($icoBmp, $size, $size)
    $ms = New-Object System.IO.MemoryStream
    $resized.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $data = $ms.ToArray()
    $ms.Dispose()
    $resized.Dispose()

    $icoWriter.Write([UInt32]$data.Length)
    $icoWriter.Write([UInt32]$offset)
    $offset += $data.Length
}

foreach ($size in $icoSizes) {
    $resized = New-Object System.Drawing.Bitmap($icoBmp, $size, $size)
    $ms = New-Object System.IO.MemoryStream
    $resized.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $data = $ms.ToArray()
    $ms.Dispose()
    $resized.Dispose()
    $icoWriter.Write($data)
}

$icoWriter.Flush()
$icoWriter.Dispose()
$icoStream.Dispose()
$icoBmp.Dispose()
$icoG.Dispose()

Write-Host "Icon ICO generated: $iconPath"
