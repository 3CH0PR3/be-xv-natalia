# Start Dev Environment for XV Natalia Isabella Backend
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Iniciando Backend XV Natalia (FastCGI :9094-:9099 + Reverb :8081 + Colas Redis)" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

$herdPhpCgi = "C:\Users\Hi\.config\herd\bin\php84\php-cgi.exe"
$phpCgiBin = if (Test-Path $herdPhpCgi) {
  $herdPhpCgi
} else {
  Join-Path (Split-Path (Get-Command php -ErrorAction Stop).Source -Parent) "php-cgi.exe"
}

$fcgiPorts = 9094..9099
if (Test-Path $phpCgiBin) {
  foreach ($port in $fcgiPorts) {
    $poolListening = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($poolListening) {
      Write-Host "Worker FastCGI PHP-CGI ya activo en 127.0.0.1:$port (omitido)." -ForegroundColor DarkGray
    } else {
      $fcgiCmd = "Write-Host 'Iniciando Worker FastCGI PHP-CGI independiente en 127.0.0.1:$port' -ForegroundColor Cyan; Remove-Item Env:PHP_FCGI_CHILDREN -ErrorAction SilentlyContinue; `$env:PHP_FCGI_MAX_REQUESTS='0'; & '$phpCgiBin' -b 127.0.0.1:$port"
      Start-Process powershell -ArgumentList "-NoExit", "-Command", $fcgiCmd
    }
  }
} else {
  Write-Warning "No se encontro php-cgi.exe en '$phpCgiBin'; Nginx usara el pool backup de Herd (127.0.0.1:9084)."
}

Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'Iniciando Servidor WebSockets Reverb en ws://0.0.0.0:8081' -ForegroundColor Magenta; php artisan reverb:start --host=0.0.0.0 --port=8081"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'Iniciando Worker Redis Fast (media-fast,default)' -ForegroundColor Yellow; php artisan queue:work redis --queue=media-fast,default --tries=3 --timeout=90"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'Iniciando Worker Redis Heavy (media-heavy, timeout=600s)' -ForegroundColor Green; php artisan queue:work redis --queue=media-heavy --tries=2 --timeout=600"

Write-Host "Entorno iniciado (API HTTPS servida por Laravel Herd en https://be-xv-natalia.test):" -ForegroundColor Green
Write-Host " - API REST (Herd): https://be-xv-natalia.test/api/v1/health" -ForegroundColor White
Write-Host " - FastCGI Pool:    127.0.0.1:9094-9099 (6 single-worker listeners, backup 127.0.0.1:9084)" -ForegroundColor White
Write-Host " - WebSockets:      ws://127.0.0.1:8081" -ForegroundColor White
Write-Host " - Worker Fast:     redis --queue=media-fast,default --tries=3 --timeout=90" -ForegroundColor White
Write-Host " - Worker Heavy:    redis --queue=media-heavy --tries=2 --timeout=600" -ForegroundColor White
