$ErrorActionPreference = 'Stop'
$applicationPath = Join-Path $PSScriptRoot 'app'
$phpExecutable = 'C:\xampp\php\php.exe'
if (!(Test-Path -LiteralPath $phpExecutable)) { throw 'PHP was not found. Update the PHP path in this script.' }
Set-Location -LiteralPath $applicationPath
& $phpExecutable artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Database migration failed.' }
& $phpExecutable artisan serve --host=127.0.0.1 --port=8000
