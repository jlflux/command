@echo off
rem One-time setup: downloads portable PHP + MariaDB into <repo>\local-dev
rem and configures everything. No admin rights required.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup.ps1"
if errorlevel 1 (
    echo.
    echo Setup failed - see the message above.
) else (
    echo.
    echo Setup finished. Double-click start.bat to launch the app.
)
pause
