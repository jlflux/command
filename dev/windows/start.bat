@echo off
rem Starts MariaDB + the PHP dev server and opens the app in your browser.
rem Run dev\windows\setup.bat once before first use.
setlocal
for %%i in ("%~dp0..\..") do set "ROOT=%%~fi"
set "DEV=%ROOT%\local-dev"
set "DBPORT=3307"
set "WEBPORT=8080"

if not exist "%DEV%\php\php.exe" (
    echo Portable PHP not found. Run setup.bat first.
    pause
    exit /b 1
)
if not exist "%DEV%\mariadb\bin\mariadbd.exe" (
    echo Portable MariaDB not found. Run setup.bat first.
    pause
    exit /b 1
)

"%DEV%\mariadb\bin\mariadb-admin.exe" --port=%DBPORT% -h 127.0.0.1 -u root ping >nul 2>&1
if errorlevel 1 (
    echo Starting MariaDB...
    start "ACC MariaDB" /min "%DEV%\mariadb\bin\mariadbd.exe" --defaults-file="%DEV%\my.ini" --console
)

set /a TRIES=0
:waitdb
"%DEV%\mariadb\bin\mariadb-admin.exe" --port=%DBPORT% -h 127.0.0.1 -u root ping >nul 2>&1
if not errorlevel 1 goto dbready
set /a TRIES+=1
if %TRIES% geq 30 (
    echo MariaDB did not start - try running setup.bat again.
    pause
    exit /b 1
)
timeout /t 1 /nobreak >nul
goto waitdb
:dbready

echo Starting PHP dev server on http://localhost:%WEBPORT% ...
start "ACC PHP" /min "%DEV%\php\php.exe" -c "%DEV%\php.ini" -S 127.0.0.1:%WEBPORT% -t "%ROOT%\public"

timeout /t 1 /nobreak >nul
start "" "http://localhost:%WEBPORT%/"

echo.
echo Athletics Command Center is running at http://localhost:%WEBPORT%
echo Two minimized windows named "ACC MariaDB" and "ACC PHP" keep it alive.
echo Use stop.bat to shut everything down.
timeout /t 8
