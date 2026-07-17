<#
Athletics Command Center — portable Windows dev environment setup.

Downloads portable PHP (NTS x64) and MariaDB into <repo>\local-dev — no
installers, no admin rights needed — then configures both, creates the
application database, and writes app\config.local.php.

Run once via setup.bat. Afterwards use start.bat / stop.bat.

Optional parameters (pass via a PowerShell prompt if the defaults fail):
  -PhpSeries      PHP release series to fetch from windows.php.net (default 8.4)
  -MariaDbVersion Exact MariaDB version from archive.mariadb.org (default 11.4.5)
  -DbPort         MariaDB port (default 3307, avoids clashing with any existing 3306)
  -WebPort        PHP dev server port (default 8080; also change start.bat if you change this)
#>
param(
    [string]$PhpSeries = '8.4',
    [string]$MariaDbVersion = '11.4.5',
    [int]$DbPort = 3307,
    [int]$WebPort = 8080
)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$dev  = Join-Path $repo 'local-dev'
$dl   = Join-Path $dev 'downloads'
New-Item -ItemType Directory -Force -Path $dev, $dl, (Join-Path $dev 'tmp') | Out-Null

# Write text files without a BOM (a BOM in config.local.php would break PHP headers).
function Write-TextFile([string]$Path, [string]$Content) {
    [IO.File]::WriteAllText($Path, $Content)
}

function Download([string]$Url, [string]$Dest) {
    Write-Host "  downloading $Url"
    Invoke-WebRequest -Uri $Url -OutFile $Dest -UseBasicParsing
}

# ---------------------------------------------------------------------------
# PHP (portable zip from windows.php.net)
# ---------------------------------------------------------------------------
$phpDir = Join-Path $dev 'php'
$phpExe = Join-Path $phpDir 'php.exe'

if (-not (Test-Path $phpExe)) {
    Write-Host "== Fetching portable PHP $PhpSeries (NTS x64) =="
    $releases = Invoke-RestMethod -Uri 'https://windows.php.net/downloads/releases/releases.json' -UseBasicParsing
    $series = $releases.$PhpSeries
    if (-not $series) {
        throw "PHP series $PhpSeries not found on windows.php.net. Available: $($releases.PSObject.Properties.Name -join ', ') — rerun with -PhpSeries."
    }
    $build = $series.PSObject.Properties | Where-Object { $_.Name -match '^nts-vs\d+-x64$' } | Select-Object -First 1
    if (-not $build) { throw "No NTS x64 build found for PHP $PhpSeries" }
    $zipName = $build.Value.zip.path
    $zipFile = Join-Path $dl $zipName
    if (-not (Test-Path $zipFile)) { Download "https://windows.php.net/downloads/releases/$zipName" $zipFile }
    Expand-Archive -Path $zipFile -DestinationPath $phpDir -Force
    Write-Host "  PHP extracted to $phpDir"
} else {
    Write-Host '== PHP already present — skipping download =='
}

Write-Host '== Writing php.ini =='
Write-TextFile (Join-Path $dev 'php.ini') @"
[PHP]
extension_dir = "$phpDir\ext"
extension=pdo_mysql
extension=mbstring
extension=fileinfo
extension=openssl
extension=curl
memory_limit = 256M
upload_max_filesize = 8M
post_max_size = 10M
display_errors = On
error_reporting = E_ALL
date.timezone = America/Chicago
session.save_path = "$dev\tmp"
upload_tmp_dir = "$dev\tmp"
"@

# Sanity check — catches a missing Visual C++ runtime early.
& $phpExe -v | Select-Object -First 1 | Write-Host
if ($LASTEXITCODE -ne 0) {
    throw 'php.exe failed to run. If Windows reported a missing VCRUNTIME140.dll, install the Visual C++ runtime first: https://aka.ms/vs/17/release/vc_redist.x64.exe'
}

# ---------------------------------------------------------------------------
# MariaDB (portable zip from archive.mariadb.org)
# ---------------------------------------------------------------------------
$mdbDir = Join-Path $dev 'mariadb'
$mariadbd = Join-Path $mdbDir 'bin\mariadbd.exe'
$mdbClient = Join-Path $mdbDir 'bin\mariadb.exe'
$mdbAdmin = Join-Path $mdbDir 'bin\mariadb-admin.exe'

if (-not (Test-Path $mariadbd)) {
    Write-Host "== Fetching portable MariaDB $MariaDbVersion =="
    $zipFile = Join-Path $dl "mariadb-$MariaDbVersion-winx64.zip"
    if (-not (Test-Path $zipFile)) {
        Download "https://archive.mariadb.org/mariadb-$MariaDbVersion/winx64-packages/mariadb-$MariaDbVersion-winx64.zip" $zipFile
    }
    $tmpExtract = Join-Path $dl 'mariadb_extract'
    if (Test-Path $tmpExtract) { Remove-Item $tmpExtract -Recurse -Force }
    Expand-Archive -Path $zipFile -DestinationPath $tmpExtract -Force
    $inner = Get-ChildItem $tmpExtract -Directory | Select-Object -First 1
    Move-Item $inner.FullName $mdbDir
    Remove-Item $tmpExtract -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "  MariaDB extracted to $mdbDir"
} else {
    Write-Host '== MariaDB already present — skipping download =='
}

Write-Host '== Writing my.ini =='
$dataDir = Join-Path $dev 'data'
$myIni = Join-Path $dev 'my.ini'
Write-TextFile $myIni @"
[mysqld]
port=$DbPort
bind-address=127.0.0.1
datadir=$($dataDir -replace '\\', '/')
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci

[client]
port=$DbPort
host=127.0.0.1
"@

if (-not (Test-Path $dataDir)) {
    Write-Host '== Initializing database files =='
    & (Join-Path $mdbDir 'bin\mariadb-install-db.exe') "--datadir=$dataDir" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'mariadb-install-db failed — see output above.' }
}

# ---------------------------------------------------------------------------
# Create the application database + user (start server briefly, then stop it)
# ---------------------------------------------------------------------------
Write-Host '== Creating application database =='
$alreadyRunning = $true
try { (New-Object Net.Sockets.TcpClient('127.0.0.1', $DbPort)).Close() } catch { $alreadyRunning = $false }

if (-not $alreadyRunning) {
    Start-Process -FilePath $mariadbd -ArgumentList "--defaults-file=`"$myIni`"", '--console' -WindowStyle Hidden | Out-Null
}
$up = $false
for ($i = 0; $i -lt 60; $i++) {
    try { (New-Object Net.Sockets.TcpClient('127.0.0.1', $DbPort)).Close(); $up = $true; break } catch { Start-Sleep -Seconds 1 }
}
if (-not $up) { throw "MariaDB did not start on port $DbPort" }

@"
CREATE DATABASE IF NOT EXISTS acc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'acc'@'localhost' IDENTIFIED BY 'acc_local_pw';
CREATE USER IF NOT EXISTS 'acc'@'127.0.0.1' IDENTIFIED BY 'acc_local_pw';
GRANT ALL PRIVILEGES ON acc.* TO 'acc'@'localhost';
GRANT ALL PRIVILEGES ON acc.* TO 'acc'@'127.0.0.1';
FLUSH PRIVILEGES;
"@ | & $mdbClient "--port=$DbPort" -h 127.0.0.1 -u root
if ($LASTEXITCODE -ne 0) { throw 'Could not create the database (mariadb client failed).' }

if (-not $alreadyRunning) {
    & $mdbAdmin "--port=$DbPort" -h 127.0.0.1 -u root shutdown
}

# ---------------------------------------------------------------------------
# app/config.local.php (gitignored) — points the app at this local database
# ---------------------------------------------------------------------------
$configLocal = Join-Path $repo 'app\config.local.php'
if (Test-Path $configLocal) {
    Write-Host '== app\config.local.php already exists — leaving it untouched =='
} else {
    Write-Host '== Writing app\config.local.php =='
    Write-TextFile $configLocal @"
<?php
declare(strict_types=1);

// Local dev overrides (gitignored). Created by dev/windows/setup.ps1.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', $DbPort);
define('DB_NAME', 'acc');
define('DB_USER', 'acc');
define('DB_PASS', 'acc_local_pw');
define('APP_ENV', 'development');
"@
}

Write-Host ''
Write-Host '=========================================================='
Write-Host ' Setup complete.'
Write-Host ' Double-click dev\windows\start.bat to launch the app.'
Write-Host " First visit opens the installer — create your school"
Write-Host ' and admin login there.'
Write-Host '=========================================================='
