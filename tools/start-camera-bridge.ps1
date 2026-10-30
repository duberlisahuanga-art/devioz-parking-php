param()
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path $PSScriptRoot -Parent
$privatePath = Join-Path $projectPath 'backend/storage/go2rtc'
if (-not (Test-Path -LiteralPath (Join-Path $privatePath 'private.json'))) { throw 'Primero ejecuta tools/configure-dvr-secret.ps1.' }
$pidFile = Join-Path $privatePath 'supervisor.json'
if (Test-Path -LiteralPath $pidFile) {
    $state = Get-Content -LiteralPath $pidFile -Raw | ConvertFrom-Json
    $existing = Get-CimInstance Win32_Process -Filter "ProcessId=$([int]$state.pid)" -ErrorAction SilentlyContinue
    if ($existing -and $existing.CommandLine -like '*camera-bridge-supervisor.ps1*') { Write-Output 'El supervisor de camaras ya esta activo.'; exit }
}
$stopFile = Join-Path $privatePath 'stop.request'
if (Test-Path -LiteralPath $stopFile) { Remove-Item -LiteralPath $stopFile }
$supervisorPath = Join-Path $PSScriptRoot 'camera-bridge-supervisor.ps1'
$arguments = @('-NoProfile','-ExecutionPolicy','Bypass','-File',('"' + $supervisorPath + '"'))
Start-Process -FilePath powershell.exe -ArgumentList $arguments -WindowStyle Hidden -RedirectStandardOutput (Join-Path $privatePath 'supervisor-output.log') -RedirectStandardError (Join-Path $privatePath 'supervisor-error.log') | Out-Null
Write-Output 'Supervisor iniciado en segundo plano. Comprueba el estado desde el modulo Camaras.'
