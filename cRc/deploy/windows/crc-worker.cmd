@echo off
rem cRc queue worker for a Windows dev PC (the Windows stand-in for Supervisor). Started at logon by the
rem "cRc queue worker" scheduled task (install-tasks.ps1). queue:work stops after an hour (--max-time) or on a crash;
rem the loop restarts it, like Supervisor's autorestart. Output goes to storage\logs\worker.log.
setlocal
cd /d "%~dp0..\.."
if "%CRC_PHP%"=="" set "CRC_PHP=php"
:loop
"%CRC_PHP%" artisan queue:work --queue=notifications,default --tries=3 --sleep=3 --max-time=3600 >> "storage\logs\worker.log" 2>&1
timeout /t 5 /nobreak > nul
goto loop
