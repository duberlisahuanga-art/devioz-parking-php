param()
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path $PSScriptRoot -Parent
$storageRoot = [IO.Path]::GetFullPath((Join-Path $projectPath 'backend/storage'))
$fixturePath = Join-Path $storageRoot ('supervisor-test-' + [Guid]::NewGuid().ToString('N'))
$privatePath = Join-Path $fixturePath 'backend/storage/go2rtc'
$toolPath = Join-Path $fixturePath 'tools'
$runtimePath = Join-Path $toolPath 'runtime/go2rtc-v1.9.14'
$supervisor = $null
$ownedBridge = $null
$checks = 0
function Assert-Bridge([bool]$Condition,[string]$Message) {
    if (-not $Condition) { throw $Message }
    $script:checks++
    Write-Output ('OK: ' + $Message)
}
function Wait-BridgeState([scriptblock]$Check) {
    for ($attempt=0; $attempt -lt 100; $attempt++) {
        if (Test-Path -LiteralPath (Join-Path $privatePath 'status.json')) {
            try {
                $state = Get-Content -LiteralPath (Join-Path $privatePath 'status.json') -Raw | ConvertFrom-Json
                if (& $Check $state) { return $state }
            } catch { }
        }
        Start-Sleep -Milliseconds 300
    }
    throw 'El supervisor no alcanzo el estado esperado.'
}
try {
    if (Get-NetTCPConnection -LocalPort 1984 -State Listen -ErrorAction SilentlyContinue) { throw 'Puerto 1984 en uso. No se alterara el servicio existente.' }
    New-Item -ItemType Directory -Path $privatePath,$runtimePath -Force | Out-Null
    foreach ($name in @('go2rtc.exe','verified.json')) {
        Copy-Item -LiteralPath (Join-Path $projectPath ('tools/runtime/go2rtc-v1.9.14/' + $name)) -Destination (Join-Path $runtimePath $name)
    }
    Copy-Item -LiteralPath (Join-Path $projectPath 'tools/camera-bridge-supervisor.ps1') -Destination $toolPath
    $configuration = @{
        api=@{ listen='127.0.0.1:1984'; username='test'; password=[Guid]::NewGuid().ToString('N'); local_auth=$true }
        rtsp=@{ listen='' }; webrtc=@{ listen='' ; ice_servers=@() }; log=@{ level='error' }; streams=@{}
    }
    [IO.File]::WriteAllText((Join-Path $privatePath 'private.json'),($configuration | ConvertTo-Json -Depth 5),[Text.UTF8Encoding]::new($false))
    $supervisorScript = Join-Path $toolPath 'camera-bridge-supervisor.ps1'
    $supervisor = Start-Process powershell.exe -ArgumentList @('-NoProfile','-ExecutionPolicy','Bypass','-File',('"' + $supervisorScript + '"')) -WindowStyle Hidden -PassThru
    $first = Wait-BridgeState { param($state) $state.healthy -eq $true }
    $ownedBridge = Get-Process -Id $first.pid
    Assert-Bridge ($first.running -and $ownedBridge.Path -eq (Join-Path $runtimePath 'go2rtc.exe')) 'Inicio oculto y salud del proceso propio'
    # Simulate a crash of this disposable child only; never stop an unrelated service.
    Stop-Process -Id $ownedBridge.Id
    $second = Wait-BridgeState { param($state) $state.healthy -eq $true -and $state.restarts -ge 1 -and $state.pid -ne $first.pid }
    $ownedBridge = Get-Process -Id $second.pid
    Assert-Bridge ($ownedBridge.Path -eq (Join-Path $runtimePath 'go2rtc.exe')) 'Recuperacion automatica tras caida del puente aislado'
    Set-Content -LiteralPath (Join-Path $privatePath 'stop.request') -Value stop
    Assert-Bridge ($supervisor.WaitForExit(15000)) 'Parada cooperativa del supervisor'
    Assert-Bridge (-not (Get-Process -Id $ownedBridge.Id -ErrorAction SilentlyContinue)) 'El hijo se cierra al detener el supervisor'
    Write-Output ($checks.ToString() + ' comprobaciones correctas.')
} finally {
    if ($supervisor -and -not $supervisor.HasExited) {
        Set-Content -LiteralPath (Join-Path $privatePath 'stop.request') -Value stop
        if (-not $supervisor.WaitForExit(12000)) { Stop-Process -Id $supervisor.Id }
    }
    if ($ownedBridge -and -not $ownedBridge.HasExited) { Stop-Process -Id $ownedBridge.Id -ErrorAction SilentlyContinue }
    $resolvedTarget = [IO.Path]::GetFullPath($fixturePath)
    if (-not $resolvedTarget.StartsWith($storageRoot + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -or (Split-Path $resolvedTarget -Leaf) -notmatch '^supervisor-test-[a-f0-9]{32}$') {
        throw 'Ruta de limpieza fuera del fixture autorizado.'
    }
    if (Test-Path -LiteralPath $resolvedTarget) { Remove-Item -LiteralPath $resolvedTarget -Recurse -Force }
}
