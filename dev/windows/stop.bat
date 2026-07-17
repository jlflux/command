@echo off
rem Stops the PHP dev server and MariaDB started by start.bat.
setlocal
for %%i in ("%~dp0..\..") do set "ROOT=%%~fi"
set "DEV=%ROOT%\local-dev"
set "DBPORT=3307"

echo Stopping PHP dev server...
taskkill /f /fi "WINDOWTITLE eq ACC PHP*" >nul 2>&1

echo Stopping MariaDB...
"%DEV%\mariadb\bin\mariadb-admin.exe" --port=%DBPORT% -h 127.0.0.1 -u root shutdown >nul 2>&1

echo Done.
timeout /t 3
