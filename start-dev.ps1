# Start Dev Environment for XV Natalia Isabella Backend
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Iniciando Backend Laravel 11 + Reverb + Redis Worker" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'Iniciando Laravel API en http://127.0.0.1:8000' -ForegroundColor Green; php artisan serve --port=8000"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'Iniciando Servidor WebSockets Reverb en ws://127.0.0.1:8080' -ForegroundColor Magenta; php artisan reverb:start --port=8080"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'Iniciando Procesador de Colas Redis' -ForegroundColor Yellow; php artisan queue:work redis"

Write-Host "Entorno iniciado en 3 procesos independientes:" -ForegroundColor Green
Write-Host " - API REST:        http://127.0.0.1:8000/api/v1/health" -ForegroundColor White
Write-Host " - WebSockets:      ws://127.0.0.1:8080" -ForegroundColor White
Write-Host " - Colas (Queues):  Redis default & media queues" -ForegroundColor White
