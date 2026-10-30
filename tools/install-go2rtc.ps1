param()
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path $PSScriptRoot -Parent
$runtimePath = Join-Path $projectPath 'tools/runtime/go2rtc-v1.9.14'
$archivePath = Join-Path $runtimePath 'go2rtc_win64.zip'
$expectedHash = 'dd4167d75cb04abe618855b7c71f8658bd009f60c1a71835d134d2c11c939907'
New-Item -ItemType Directory -Path $runtimePath -Force | Out-Null
if (-not (Test-Path -LiteralPath $archivePath)) {
    Invoke-WebRequest -Uri 'https://github.com/AlexxIT/go2rtc/releases/download/v1.9.14/go2rtc_win64.zip' -OutFile $archivePath -UseBasicParsing
}
if ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash) {
    throw 'El archivo descargado no coincide con el SHA256 oficial. No se ejecutara.'
}
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    $entry = @($archive.Entries | Where-Object { $_.Name -eq 'go2rtc.exe' })
    if ($entry.Count -ne 1) { throw 'El paquete no contiene un ejecutable unico.' }
    [IO.Compression.ZipFileExtensions]::ExtractToFile($entry[0], (Join-Path $runtimePath 'go2rtc.exe'), $true)
} finally { $archive.Dispose() }
$binaryHash = (Get-FileHash -LiteralPath (Join-Path $runtimePath 'go2rtc.exe') -Algorithm SHA256).Hash.ToLowerInvariant()
@{ version = '1.9.14'; sha256 = $binaryHash; archive_sha256 = $expectedHash } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimePath 'verified.json') -Encoding UTF8
Write-Output 'go2rtc 1.9.14 instalado y verificado en tools/runtime. No se modificaron servicios de Windows.'
