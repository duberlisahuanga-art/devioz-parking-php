param()
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$projectPath = Split-Path $PSScriptRoot -Parent
$source = [Drawing.Image]::FromFile((Join-Path $projectPath 'frontend/assets/favicon.png'))
$destination = Join-Path $projectPath 'frontend/assets/pwa'
New-Item -ItemType Directory -Path $destination -Force | Out-Null
try {
    foreach ($size in @(180,192,512)) {
        $bitmap = New-Object Drawing.Bitmap($size,$size)
        $graphics = [Drawing.Graphics]::FromImage($bitmap)
        try {
            $graphics.Clear([Drawing.Color]::White)
            $graphics.InterpolationMode = [Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $ratio = [Math]::Min($size * 0.84 / $source.Width, $size * 0.84 / $source.Height)
            $width = [int]($source.Width * $ratio)
            $height = [int]($source.Height * $ratio)
            $graphics.DrawImage($source,[int](($size-$width)/2),[int](($size-$height)/2),$width,$height)
            $name = if ($size -eq 180) {'apple-touch-icon.png'} else {'icon-' + $size + '.png'}
            $bitmap.Save((Join-Path $destination $name),[Drawing.Imaging.ImageFormat]::Png)
        } finally { $graphics.Dispose(); $bitmap.Dispose() }
    }
} finally { $source.Dispose() }
Write-Output 'Iconos PWA generados desde el logo existente.'
