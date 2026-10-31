$ErrorActionPreference = 'Stop'
$applicationPath = Join-Path $PSScriptRoot 'app'
$phpExecutable = 'C:\xampp\php\php.exe'
if (!(Test-Path -LiteralPath $phpExecutable)) { throw 'PHP was not found. Update the PHP path in this script.' }
Set-Location -LiteralPath $applicationPath
& $phpExecutable artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Database migration failed.' }
$scheduler = Start-Process -FilePath $phpExecutable -ArgumentList 'artisan','schedule:work' -WorkingDirectory $applicationPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $applicationPath 'storage\logs\scheduler.log') -RedirectStandardError (Join-Path $applicationPath 'storage\logs\scheduler-error.log')
try {
    Set-Location -LiteralPath (Join-Path $applicationPath 'public')
    $router = Join-Path $applicationPath 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    & $phpExecutable -S 127.0.0.1:8001 -t . $router
} finally {
    if (!$scheduler.HasExited) { Stop-Process -Id $scheduler.Id }
}
