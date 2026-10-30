param()
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path $PSScriptRoot -Parent
$privatePath = Join-Path $projectPath 'backend/storage/go2rtc'
if (-not (Test-Path -LiteralPath $privatePath)) { Write-Output 'No hay una instalacion configurada.'; exit }
Set-Content -LiteralPath (Join-Path $privatePath 'stop.request') -Value 'stop' -Encoding ASCII
Write-Output 'Cierre solicitado al supervisor. Espera unos segundos antes de volver a iniciarlo.'
