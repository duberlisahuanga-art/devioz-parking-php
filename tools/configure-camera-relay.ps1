param([Parameter(Mandatory=$true)][string]$TurnUrl)
$ErrorActionPreference = 'Stop'
if ($TurnUrl -notmatch '^turns?:[a-zA-Z0-9.-]+(:[0-9]{1,5})?(\?transport=(tcp|udp))?$') { throw 'Indica una URL TURN del proveedor, sin usuario ni contrasena.' }
$privatePath = Join-Path (Split-Path $PSScriptRoot -Parent) 'backend/storage/go2rtc'
New-Item -ItemType Directory -Path $privatePath -Force | Out-Null
$secret = Read-Host 'Secreto TURN REST del servidor (entrada oculta)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $value = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    if ($value.Length -lt 32) { throw 'El secreto debe tener al menos 32 caracteres.' }
    $configuration = @{stun_urls=@();turn_urls=@($TurnUrl);turn_shared_secret=$value;ttl_seconds=600;transport_policy='all'}
    $pending = Join-Path $privatePath 'ice.pending.json'
    [IO.File]::WriteAllText($pending,($configuration | ConvertTo-Json -Depth 5),[Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $pending -Destination (Join-Path $privatePath 'ice.private.json') -Force
    Write-Output 'Configuracion del navegador guardada. Falta configurar y validar la conectividad del lado de go2rtc con el proveedor.'
} finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer); $value=$null }
