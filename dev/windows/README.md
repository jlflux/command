# Local development on Windows (portable, no installers)

Runs the app on your own machine with portable PHP and MariaDB living
entirely inside `<repo>\local-dev\` (gitignored). Nothing is installed
system-wide and no admin rights are needed.

## First-time setup

1. Get the code onto your machine (GitHub → green **Code** button →
   **Download ZIP**, or `git clone`), and note the folder.
2. Double-click **`dev\windows\setup.bat`**. It downloads portable PHP
   (from windows.php.net) and MariaDB (from archive.mariadb.org), configures
   both, creates the `acc` database, and writes `app\config.local.php`
   pointing the app at it. Takes a few minutes — the MariaDB zip is ~100 MB.
3. Double-click **`dev\windows\start.bat`**. Your browser opens
   `http://localhost:8080` — the first visit lands on the installer, where
   you create your school and admin login. After that you're in the app.

Day to day: `start.bat` to run, `stop.bat` to shut down. Your data persists
in `local-dev\data\` between runs.

## Troubleshooting

- **`php.exe` complains about a missing `VCRUNTIME140.dll`** — install the
  Microsoft Visual C++ runtime (one-time):
  <https://aka.ms/vs/17/release/vc_redist.x64.exe>
- **"Setup failed" during a download** — a firewall/proxy may block the
  download hosts. The URLs used are printed in the console; you can download
  those two zips manually into `local-dev\downloads\` and rerun `setup.bat`
  (it picks up already-downloaded files).
- **PHP download 404s** — the pinned series rotated. Open a PowerShell in
  `dev\windows` and run
  `powershell -ExecutionPolicy Bypass -File setup.ps1 -PhpSeries 8.3`.
  Similarly `-MariaDbVersion 11.4.x` if the MariaDB version is the problem.
- **Port already in use** — 8080 (web) or 3307 (DB) is taken by something
  else. Rerun `setup.ps1` with `-WebPort`/`-DbPort` and change the matching
  values at the top of `start.bat` / `stop.bat`.
- **Blank page / errors** — `app\config.local.php` sets
  `APP_ENV=development`, so PHP prints errors to the browser. The two
  minimized console windows ("ACC PHP", "ACC MariaDB") show server logs.

## What setup creates

```
local-dev\
  php\          Portable PHP (NTS x64)
  php.ini       PHP config (pdo_mysql, mbstring, fileinfo enabled)
  mariadb\      Portable MariaDB
  my.ini        MariaDB config (port 3307, utf8mb4)
  data\         Your database files (persists between runs)
  downloads\    Downloaded zips (safe to delete after setup)
  tmp\          PHP sessions + upload temp
app\config.local.php   Local DB credentials (gitignored)
```
