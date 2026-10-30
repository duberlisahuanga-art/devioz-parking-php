param()
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$runtimeRoot = Join-Path (Split-Path $PSScriptRoot -Parent) 'tools/runtime'
$packages = @(
    @{name='cloudflared';version='2026.10.6';file='cloudflared.exe';archive='cloudflared.exe';hash='d521b3bf68982165f0d06d76732f22d9a105ef057ff3ae4c0986f3a23190950f';url='https://github.com/cloudflare/cloudflared/releases/download/2026.10.6/cloudflared-windows-amd64.exe'},
    @{name='ffmpeg';version='9.0.2';file='ffmpeg.exe';archive='ffmpeg.zip';hash='60f467265b1e312373dbcd92200c2618a74850f98d3d078e94296bb3fa2047ba';url='https://www.gyan.dev/ffmpeg/builds/packages/ffmpeg-9.0.2-essentials_build.zip'}
)
Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($package in $packages) {
    $destination = Join-Path $runtimeRoot ($package.name + '-' + $package.version)
    New-Item -ItemType Directory -Path $destination -Force | Out-Null
    $archivePath = Join-Path $destination $package.archive
    if (-not (Test-Path -LiteralPath $archivePath)) {
        $pendingPath = $archivePath + '.partial'
        Write-Output ('Descargando ' + $package.name + ' ' + $package.version + '...')
        Invoke-WebRequest -Uri $package.url -OutFile $pendingPath -UseBasicParsing -TimeoutSec 180
        if ((Get-FileHash -LiteralPath $pendingPath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $package.hash) { throw 'SHA256 no coincide; no se ejecutara el archivo.' }
        Move-Item -LiteralPath $pendingPath -Destination $archivePath -Force
    }
    if ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $package.hash) { throw 'Paquete local no verificado.' }
    if ($package.name -eq 'ffmpeg') {
        $archive = [IO.Compression.ZipFile]::OpenRead($archivePath)
        try {
            foreach ($name in @('ffmpeg.exe','ffprobe.exe','LICENSE')) {
                $entries = @($archive.Entries | Where-Object Name -eq $name)
                if ($entries.Count -ne 1) { throw ('Archivo esperado no encontrado: ' + $name) }
                [IO.Compression.ZipFileExtensions]::ExtractToFile($entries[0], (Join-Path $destination $name), $true)
            }
        } finally { $archive.Dispose() }
    }
    $executable = Join-Path $destination $package.file
    @{version=$package.version;source=$package.url;archive_sha256=$package.hash;sha256=(Get-FileHash -LiteralPath $executable -Algorithm SHA256).Hash.ToLowerInvariant()} |
        ConvertTo-Json | Set-Content -LiteralPath (Join-Path $destination 'verified.json') -Encoding UTF8
    Write-Output ($package.name + ' instalado y verificado: ' + $destination)
}
Write-Output 'Instalacion portatil completada. No se publicaron tuneles ni se modificaron servicios de Windows.'
