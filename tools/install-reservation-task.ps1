$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$taskName = 'DEVIOZ-Parking-ReservationLifecycle'
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    throw "Ya existe la tarea $taskName. Revisa su configuración antes de reemplazarla."
}
$launcher = Join-Path $projectRoot 'tools/reservation-lifecycle.php'
$action = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' `
    -Argument ('"' + $launcher + '"') `
    -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) `
    -LogonType S4U -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Cierra mensualidades vencidas con vehículo fuera y envía avisos; no libera plazas ocupadas.'
Start-ScheduledTask -TaskName $taskName
Write-Output 'Tarea instalada y ejecución inicial solicitada.'
