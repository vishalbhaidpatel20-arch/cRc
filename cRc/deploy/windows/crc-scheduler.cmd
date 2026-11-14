@echo off
rem cRc scheduler for a Windows dev PC (the Windows stand-in for the cron line). Started at logon by the
rem "cRc scheduler" scheduled task (install-tasks.ps1). schedule:work runs routes/console.php every minute
rem (calendar reminders); the loop restarts it if it ever exits. Output goes to storage\logs\scheduler.log.
setlocal
cd /d "%~dp0..\.."
if "%CRC_PHP%"=="" set "CRC_PHP=php"
:loop
"%CRC_PHP%" artisan schedule:work >> "storage\logs\scheduler.log" 2>&1
timeout /t 5 /nobreak > nul
goto loop
