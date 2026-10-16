$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$taskName = 'DEVIOZ-Parking-RecoveryMail'
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    throw "Ya existe $taskName. No se reemplazó."
}
$launcher = Join-Path $projectRoot 'tools/recovery-mail-worker.php'
$action = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' -Argument ('"' + $launcher + '"') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType S4U -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Entrega enlaces de recuperación por SMTP; token cifrado, caducidad y reintentos limitados.' | Out-Null
Write-Output 'Tarea de correo instalada con intervalo de un minuto.'
