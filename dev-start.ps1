# dev-start.ps1 — Arranca el backend completo para desarrollo local en Windows
# Horizon no funciona en Windows (requiere pcntl de Linux).
# Este script arranca el server + el queue worker en paralelo.

$backendPath = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "=== XV Natalia Backend - Dev Start ===" -ForegroundColor Cyan
Write-Host "Iniciando queue worker (media-fast, media-heavy, default)..." -ForegroundColor Yellow

# Worker en ventana separada
Start-Process powershell -ArgumentList @(
    "-NoExit",
    "-Command",
    "cd '$backendPath'; Write-Host 'Queue Worker activo' -ForegroundColor Green; php artisan queue:work redis --queue=media-fast,media-heavy,default --timeout=600 --tries=2 --sleep=1"
) -WindowStyle Normal

Start-Sleep -Seconds 1
Write-Host "Iniciando servidor Laravel..." -ForegroundColor Yellow

# Server en esta misma ventana
Set-Location $backendPath
php artisan serve
