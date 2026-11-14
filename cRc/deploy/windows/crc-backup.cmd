@echo off
rem cRc daily database backup for a Windows dev PC. Run by the "cRc DB backup" scheduled task (install-tasks.ps1).
rem Writes crc-YYYYMMDD-HHMMSS.sql.gz to CRC_BACKUP_PATH from .env and keeps CRC_BACKUP_KEEP_DAYS days.
rem Output goes to storage\logs\backup.log.
setlocal
cd /d "%~dp0..\.."
if "%CRC_PHP%"=="" set "CRC_PHP=php"
echo [%date% %time%] >> "storage\logs\backup.log"
"%CRC_PHP%" artisan crc:backup-db >> "storage\logs\backup.log" 2>&1
exit /b %errorlevel%
