<#
    Configures the local PHP 8.3 (ZTS x64) installation for the IT Helpdesk backend.

    PHP was installed via winget and ships without a php.ini, so none of the
    extensions Laravel needs are active. Re-run this script after any PHP
    upgrade, because winget replaces the package directory and discards both
    php.ini and the SQL Server driver DLLs.
#>

[CmdletBinding()]
param(
    [string]$DriverVersion = '5.13.3'
)

$ErrorActionPreference = 'Stop'

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCommand) {
    throw 'php.exe was not found on PATH. Install PHP 8.3 before running this script.'
}
$phpExe = $phpCommand.Source

$phpDir = Split-Path $phpExe
$extDir = Join-Path $phpDir 'ext'
$iniPath = Join-Path $phpDir 'php.ini'

Write-Host "PHP directory: $phpDir" -ForegroundColor Cyan

# --- SQL Server drivers ---------------------------------------------------
# The bundled PHP distribution has no sqlsrv support; fetch it from Microsoft.
# Microsoft ships version-stamped filenames, but they are installed under the
# plain names so php.ini stays valid across driver upgrades.
$drivers = @{
    'php_sqlsrv_83_ts_x64.dll'     = 'php_sqlsrv.dll'
    'php_pdo_sqlsrv_83_ts_x64.dll' = 'php_pdo_sqlsrv.dll'
}
$missing = $drivers.Values | Where-Object { -not (Test-Path (Join-Path $extDir $_)) }

if ($missing) {
    Write-Host "Downloading Microsoft PHP drivers for SQL Server $DriverVersion..." -ForegroundColor Cyan
    $staging = Join-Path $env:TEMP "msphpsql-$DriverVersion"
    New-Item -ItemType Directory -Force -Path $staging | Out-Null
    $zip = Join-Path $staging 'Windows.zip'

    Invoke-WebRequest -UseBasicParsing `
        -Uri "https://github.com/microsoft/msphpsql/releases/download/v$DriverVersion/Windows_${DriverVersion}RTW.zip" `
        -OutFile $zip

    Expand-Archive -Path $zip -DestinationPath (Join-Path $staging 'extracted') -Force

    foreach ($source in $drivers.Keys) {
        $found = Get-ChildItem (Join-Path $staging 'extracted') -Recurse -Filter $source | Select-Object -First 1
        if (-not $found) { throw "Driver $source was not present in the downloaded archive." }
        Copy-Item $found.FullName -Destination (Join-Path $extDir $drivers[$source]) -Force
        Write-Host "  installed $($drivers[$source])" -ForegroundColor Green
    }
}
else {
    Write-Host 'SQL Server drivers already present.' -ForegroundColor Green
}

# --- php.ini --------------------------------------------------------------
if (-not (Test-Path $iniPath)) {
    Copy-Item (Join-Path $phpDir 'php.ini-development') $iniPath
    Write-Host 'Created php.ini from php.ini-development.' -ForegroundColor Green
}

$marker = '; === IT Helpdesk configuration ==='
if ((Get-Content $iniPath -Raw) -notmatch [regex]::Escape($marker)) {
    # extension_dir must be absolute: a relative path resolves against the
    # working directory, which breaks artisan when run from the backend folder.
    $block = @"

$marker
extension_dir = "$extDir"

extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sodium
extension=zip

extension=sqlsrv
extension=pdo_sqlsrv

date.timezone = Asia/Colombo
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 24M
max_execution_time = 120
"@
    Add-Content -Path $iniPath -Value $block
    Write-Host 'Appended IT Helpdesk settings to php.ini.' -ForegroundColor Green
}
else {
    Write-Host 'php.ini already configured.' -ForegroundColor Green
}

# --- Verify ---------------------------------------------------------------
Write-Host "`nLoaded extensions:" -ForegroundColor Cyan
& $phpExe -m | Select-String -Pattern '^(curl|fileinfo|gd|intl|mbstring|openssl|pdo_sqlsrv|sqlsrv|sodium|zip)$'
