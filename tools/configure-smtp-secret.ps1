$ErrorActionPreference = 'Stop'
$secret = Read-Host 'Clave de aplicacion de Gmail: 16 caracteres (entrada oculta)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $env:DEVIOZ_SMTP_SETUP_SECRET = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    & C:\xampp\php\php.exe (Join-Path $PSScriptRoot 'configure-smtp-secret.php')
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo guardar el secreto SMTP.' }
} finally {
    Remove-Item Env:\DEVIOZ_SMTP_SETUP_SECRET -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $secret.Dispose()
}
