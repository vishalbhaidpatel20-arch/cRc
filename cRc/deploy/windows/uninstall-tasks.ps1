# Stops and removes the cRc scheduled tasks created by install-tasks.ps1 (backups already written are kept).
# Usage: powershell -ExecutionPolicy Bypass -File deploy\windows\uninstall-tasks.ps1

# Stopping a task does not always end its php child, so first end the loops started from this folder's .cmd files
# and their php children (only these: workers of other projects are left alone).
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$all = Get-CimInstance Win32_Process
$loops = $all | Where-Object { $_.Name -eq 'cmd.exe' -and $_.CommandLine -and $_.CommandLine.Contains($dir) }
foreach ($loop in $loops) {
    $all | Where-Object { $_.ParentProcessId -eq $loop.ProcessId } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue; "Stopped $($_.Name) $($_.ProcessId)" }
    Stop-Process -Id $loop.ProcessId -Force -ErrorAction SilentlyContinue
}

foreach ($name in 'cRc queue worker', 'cRc scheduler', 'cRc DB backup') {
    if (Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue) {
        Stop-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
        Unregister-ScheduledTask -TaskName $name -Confirm:$false
        "Removed: $name"
    }
}
