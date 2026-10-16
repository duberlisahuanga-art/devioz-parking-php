$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$taskName = 'DEVIOZ-Parking-AuditBackup'
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    throw "La tarea $taskName ya existe; no se reemplazó."
}
$launcher = Join-Path $projectRoot 'tools/audit-backup.php'
$action = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' `
    -Argument ('"' + $launcher + '"') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Daily -At '02:00'
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) `
    -LogonType S4U -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 15) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Respaldo privado diario del historial de auditoría; sin borrar eventos ni copias.' | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Output 'Respaldo diario a las 02:00 del equipo instalado; ejecución inicial solicitada.'
