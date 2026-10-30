param(
    [string]$DvrHost = '192.168.1.100',
    [string]$LanAddress = '192.168.1.113',
    [ValidateRange(1,65535)][int]$RtspPort = 554
)
$ErrorActionPreference = 'Stop'
foreach ($addressValue in @($DvrHost,$LanAddress)) {
    if ($addressValue -notmatch '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)' -or -not [Net.IPAddress]::TryParse($addressValue, [ref]([Net.IPAddress]$null))) {
        throw 'Indica direcciones IPv4 privadas validas de la red local.'
    }
}
if (-not (Get-NetIPAddress -AddressFamily IPv4 | Where-Object IPAddress -eq $LanAddress)) { throw 'La IP del servidor no esta asignada a este equipo.' }
$projectPath = Split-Path $PSScriptRoot -Parent
$privatePath = Join-Path $projectPath 'backend/storage/go2rtc'
New-Item -ItemType Directory -Path $privatePath -Force | Out-Null
Set-Content -LiteralPath (Join-Path $privatePath '.htaccess') -Value 'Require all denied' -Encoding ASCII
$userName = Read-Host 'Usuario del DVR con permiso de visualizacion RTSP'
if ([string]::IsNullOrWhiteSpace($userName)) { throw 'El usuario no puede estar vacio.' }
$securePassword = Read-Host 'Contrasena del DVR (entrada oculta)' -AsSecureString
$secretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $passwordValue = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretPointer)
    if ([string]::IsNullOrEmpty($passwordValue)) { throw 'La contrasena no puede estar vacia.' }
    $randomBytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($randomBytes) } finally { $generator.Dispose() }
    $apiSecret = [Convert]::ToBase64String($randomBytes)
    $streams = [ordered]@{}
    for ($channelIndex = 1; $channelIndex -le 4; $channelIndex++) {
        foreach ($qualityName in @('sub','main')) {
            $suffix = if ($qualityName -eq 'sub') { '02' } else { '01' }
            $source = 'rtsp://' + [Uri]::EscapeDataString($userName) + ':' + [Uri]::EscapeDataString($passwordValue) + '@' + $DvrHost + ':' + $RtspPort + '/Streaming/Channels/' + $channelIndex + $suffix
            $streams["dvr_ch${channelIndex}_$qualityName"] = @($source)
        }
    }
    $configuration = [ordered]@{
        api = @{ listen='127.0.0.1:1984'; username='devioz'; password=$apiSecret; local_auth=$true; allow_paths=@('/api','/api/streams','/api/webrtc') }
        rtsp = @{ listen='' }
        webrtc = @{ listen=':8555'; candidates=@("${LanAddress}:8555",'127.0.0.1:8555'); ice_servers=@(); filters=@{ loopback=$true; ips=@($LanAddress,'127.0.0.1'); networks=@('udp4','tcp4') } }
        log = @{ level='warn' }
        streams = $streams
        devioz = @{ dvr_host=$DvrHost; lan_address=$LanAddress; rtsp_port=$RtspPort; version='1.9.14'; configured_at=[DateTime]::UtcNow.ToString('o') }
    }
    $destination = Join-Path $privatePath 'private.json'
    $pendingPath = Join-Path $privatePath 'private.pending.json'
    [IO.File]::WriteAllText($pendingPath, ($configuration | ConvertTo-Json -Depth 8), [Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $pendingPath -Destination $destination -Force
    Write-Output 'Credenciales privadas guardadas. No se mostraron ni se guardaron en Git.'
    Write-Output 'Siguiente: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/start-camera-bridge.ps1'
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPointer)
    $passwordValue = $null
    $apiSecret = $null
}
