param()
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path $PSScriptRoot -Parent
$privatePath = Join-Path $projectPath 'backend/storage/go2rtc'
$runtimePath = Join-Path $projectPath 'tools/runtime/go2rtc-v1.9.14'
$binaryPath = Join-Path $runtimePath 'go2rtc.exe'
$configPath = Join-Path $privatePath 'private.json'
$stopPath = Join-Path $privatePath 'stop.request'
$statusPath = Join-Path $privatePath 'status.json'
$mutex = New-Object Threading.Mutex($false,'Local\DeviozCameraBridgeSupervisor')
if (-not $mutex.WaitOne(0)) { $mutex.Dispose(); exit }
$bridgeProcess = $null
try {
    $manifest = Get-Content -LiteralPath (Join-Path $runtimePath 'verified.json') -Raw | ConvertFrom-Json
    if ((Get-FileHash -LiteralPath $binaryPath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $manifest.sha256) { throw 'Ejecutable no verificado.' }
    $configuration = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
    if ($configuration.api.listen -ne '127.0.0.1:1984' -or -not $configuration.api.local_auth) { throw 'Configuracion de API local no valida.' }
    if (Get-NetTCPConnection -LocalPort 1984 -State Listen -ErrorAction SilentlyContinue) { throw 'El puerto 1984 esta ocupado. No se detendra otro servicio.' }
    @{ pid=$PID; started_at=[DateTime]::UtcNow.ToString('o') } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $privatePath 'supervisor.json') -Encoding UTF8
    $headers = @{ Authorization='Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($configuration.api.username + ':' + $configuration.api.password)) }
    $restarts = 0
    while (-not (Test-Path -LiteralPath $stopPath)) {
        if ($restarts -ge 20) { throw 'Se alcanzo el limite de reinicios; revisar el diagnostico privado.' }
        foreach ($logName in @('bridge-output.log','bridge-error.log')) {
            $logPath = Join-Path $privatePath $logName
            if (Test-Path -LiteralPath $logPath) {
                Move-Item -LiteralPath $logPath -Destination ($logPath + '.previous') -Force
            }
        }
        $bridgeProcess = Start-Process -FilePath $binaryPath -ArgumentList @('-config',('"' + $configPath + '"')) -WindowStyle Hidden -RedirectStandardOutput (Join-Path $privatePath 'bridge-output.log') -RedirectStandardError (Join-Path $privatePath 'bridge-error.log') -PassThru
        $failures = 0
        $startedAt = [DateTime]::UtcNow
        while (-not $bridgeProcess.HasExited -and -not (Test-Path -LiteralPath $stopPath)) {
            Start-Sleep -Seconds 3
            $healthy = $false
            try { $reply = Invoke-WebRequest -Uri 'http://127.0.0.1:1984/api' -Headers $headers -TimeoutSec 2 -UseBasicParsing; $healthy = $reply.StatusCode -eq 200 } catch { }
            if ($healthy) { $failures = 0 } else { $failures++ }
            @{ running=(-not $bridgeProcess.HasExited); healthy=$healthy; pid=$bridgeProcess.Id; restarts=$restarts; checked_at=[DateTime]::UtcNow.ToString('o') } | ConvertTo-Json | Set-Content -LiteralPath $statusPath -Encoding UTF8
            $oversized = Get-ChildItem -LiteralPath $privatePath -Filter 'bridge-*.log' | Where-Object Length -gt 10485760
            if ($failures -ge 3 -or $oversized) { break }
        }
        if (-not $bridgeProcess.HasExited) { Stop-Process -Id $bridgeProcess.Id -ErrorAction SilentlyContinue; $bridgeProcess.WaitForExit(5000) | Out-Null }
        if (Test-Path -LiteralPath $stopPath) { break }
        if (([DateTime]::UtcNow - $startedAt).TotalHours -ge 1) { $restarts = 0 }
        $restarts++
        $delaySeconds = [Math]::Min(30,[Math]::Pow(2,[Math]::Min($restarts,5)))
        for ($waitIndex = 0; $waitIndex -lt $delaySeconds -and -not (Test-Path -LiteralPath $stopPath); $waitIndex++) { Start-Sleep -Seconds 1 }
    }
} catch {
    @{ running=$false; healthy=$false; error='Revisar configuracion, puertos y ejecutable con el operador.'; checked_at=[DateTime]::UtcNow.ToString('o') } | ConvertTo-Json | Set-Content -LiteralPath $statusPath -Encoding UTF8
} finally {
    if ($bridgeProcess -and -not $bridgeProcess.HasExited) { Stop-Process -Id $bridgeProcess.Id -ErrorAction SilentlyContinue }
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}
