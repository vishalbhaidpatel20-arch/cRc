# Registers the cRc background tasks in Windows Task Scheduler for the current user (no admin rights needed).
# This is the dev-PC stand-in for the server's cron line and Supervisor program:
#   "cRc queue worker"  at logon, runs crc-worker.cmd     (php artisan queue:work, restarted in a loop)
#   "cRc scheduler"     at logon, runs crc-scheduler.cmd  (php artisan schedule:work: calendar reminders every minute)
#   "cRc DB backup"     daily at 13:00, runs crc-backup.cmd (php artisan crc:backup-db); if the PC was off, it runs at the next start
# Each runs hidden (conhost --headless), so no console window pops up. Logs: storage\logs\worker.log, scheduler.log, backup.log.
#
# Usage (from the cRc folder):   powershell -ExecutionPolicy Bypass -File deploy\windows\install-tasks.ps1
# Remove again:                  powershell -ExecutionPolicy Bypass -File deploy\windows\uninstall-tasks.ps1
param(
    [string]$BackupTime = '13:00',
    [switch]$NoStart
)
$ErrorActionPreference = 'Stop'

$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$app = (Resolve-Path (Join-Path $dir '..\..')).Path
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'php is not on PATH; the tasks call "php artisan ...".' }

$user = "$env:USERDOMAIN\$env:USERNAME"
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited

function New-Action([string]$script) {
    New-ScheduledTaskAction -Execute 'conhost.exe' -Argument "--headless cmd.exe /c `"`"$dir\$script`"`"" -WorkingDirectory $app
}

# Long-running tasks: no time limit, keep running on battery, only one copy, restart if the task itself fails.
$longRunning = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
$daily = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -StartWhenAvailable

$atLogon = New-ScheduledTaskTrigger -AtLogOn -User $user

Register-ScheduledTask -TaskName 'cRc queue worker' -Description 'cRc: php artisan queue:work (notifications queue)' `
    -Action (New-Action 'crc-worker.cmd') -Trigger $atLogon -Settings $longRunning -Principal $principal -Force | Out-Null
Register-ScheduledTask -TaskName 'cRc scheduler' -Description 'cRc: php artisan schedule:work (calendar reminders)' `
    -Action (New-Action 'crc-scheduler.cmd') -Trigger $atLogon -Settings $longRunning -Principal $principal -Force | Out-Null
Register-ScheduledTask -TaskName 'cRc DB backup' -Description 'cRc: php artisan crc:backup-db (daily gzipped mysqldump)' `
    -Action (New-Action 'crc-backup.cmd') -Trigger (New-ScheduledTaskTrigger -Daily -At $BackupTime) -Settings $daily `
    -Principal $principal -Force | Out-Null

if (-not $NoStart) {
    Start-ScheduledTask -TaskName 'cRc queue worker'
    Start-ScheduledTask -TaskName 'cRc scheduler'
}

Get-ScheduledTask -TaskName 'cRc *' | Select-Object TaskName, State | Format-Table -AutoSize
