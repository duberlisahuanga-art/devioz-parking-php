param(
    [Parameter(Mandatory=$true)][string]$Hostname,
    [Parameter(Mandatory=$true)][Guid]$TunnelId,
    [Parameter(Mandatory=$true)][string]$CredentialsFile
)
$ErrorActionPreference = 'Stop'
if ($Hostname.Length -gt 253 -or $Hostname -notmatch '^(?=.{1,253}$)([a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$') { throw 'Indica un nombre de dominio valido, sin protocolo ni ruta.' }
if (-not (Test-Path -LiteralPath $CredentialsFile -PathType Leaf)) { throw 'Falta el archivo de credenciales del tunel creado en tu cuenta.' }
try { $credentials = Get-Content -LiteralPath $CredentialsFile -Raw | ConvertFrom-Json } catch { throw 'El archivo de credenciales no tiene un formato valido.' }
if ($credentials.TunnelID -ne $TunnelId.ToString() -or [string]::IsNullOrWhiteSpace($credentials.TunnelSecret) -or $credentials.AccountTag -notmatch '^[a-fA-F0-9]{32}$') {
    throw 'Las credenciales no corresponden al identificador del tunel indicado.'
}
$privatePath = Join-Path (Split-Path $PSScriptRoot -Parent) 'backend/storage/cloudflared'
New-Item -ItemType Directory -Path $privatePath -Force | Out-Null
$configuration = @{
    tunnel=$TunnelId.ToString()
    'credentials-file'=[IO.Path]::GetFullPath($CredentialsFile)
    ingress=@(@{hostname=$Hostname.ToLowerInvariant();service='http://127.0.0.1:80'},@{service='http_status:404'})
}
$destination = Join-Path $privatePath 'config.json'
[IO.File]::WriteAllText($destination,($configuration | ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
Write-Output 'Configuracion privada preparada. No se inicio ni se publico el tunel.'
Write-Output 'Antes de publicarlo: configurar TRUSTED_PROXY_IPS=127.0.0.1 y validar acceso, HTTPS y conectividad de video.'
