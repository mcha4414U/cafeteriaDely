$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$phpDely = (Get-Command php -ErrorAction Stop).Source
$trabajoDely = $null
try {
    $trabajoDely = Start-Process -FilePath $phpDely -ArgumentList 'artisan','schedule:work' -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -PassThru
    Write-Host 'Cafetería Dely: http://127.0.0.1:8000'
    Write-Host 'Servidor y alertas automáticas activos. Usa Ctrl+C para terminar.'
    & $phpDely artisan serve --host=127.0.0.1 --port=8000
} finally {
    if ($trabajoDely -and -not $trabajoDely.HasExited) { Stop-Process -Id $trabajoDely.Id -ErrorAction SilentlyContinue }
}
