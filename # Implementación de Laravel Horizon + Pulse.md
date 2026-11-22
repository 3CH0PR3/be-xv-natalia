# Implementación de Laravel Horizon + Pulse — xv-natalia

## Contexto del sistema

| App | Ruta | Queue Redis DB | Reverb puerto | Supervisor actual |
|---|---|---|---|---|
| xv-natalia | `/var/www/xv-natalia/be-xv-natalia` | DB7 | 8083 | `xv-natalia-worker` (5 procs) + `xv-natalia-reverb` |

**URL base:** `https://api.xv-natalia-isabela.com`

---

## Paso 1 — Instalar Horizon

```bash
cd /var/www/xv-natalia/be-xv-natalia
sudo -u www-data composer require laravel/horizon
sudo -u www-data php artisan horizon:install
```

`horizon:install` hace tres cosas:
- Publica `config/horizon.php`
- Publica los assets del dashboard en `public/vendor/horizon`
- Registra el service provider automáticamente

**Verificar:**
```bash
ls config/horizon.php
ls public/vendor/horizon/
```

---

## Paso 2 — Configurar config/horizon.php

Localiza la clave `environments` en `config/horizon.php` y reemplázala completa:

```php
'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection'   => 'redis',
            'queue'        => ['default'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'memory'       => 128,
            'tries'        => 3,
            'timeout'      => 90,
            'nice'         => 0,
        ],
    ],
    'local' => [
        'supervisor-default' => [
            'connection'   => 'redis',
            'queue'        => ['default'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'memory'       => 128,
            'tries'        => 3,
            'timeout'      => 90,
        ],
    ],
],
```

> `maxProcesses: 3` respeta el límite del VPS de 1GB. Horizon arranca con 1 worker y escala hasta 3 según la carga real.

---

## Paso 3 — Proteger el dashboard de Horizon

Horizon expone jobs, payloads y datos internos — hay que bloquearlo.

xv-natalia usa `X-Host-Pin` como mecanismo de autenticación a lo largo de toda la app. Es consistente usarlo aquí también.

Edita `app/Providers/AppServiceProvider.php`, agrega al final del método `boot()`:

```php
use Laravel\Horizon\Horizon;

// Al final de boot():
Horizon::auth(function ($request) {
    return $request->header('X-Host-Pin') === config('xv.host_pin');
});
```

**Para acceder al dashboard desde el browser** usa una extensión como [ModHeader](https://modheader.com) (Chrome/Brave/Edge) que te permite inyectar headers en cada request, o bien
accede con curl:

```bash
curl -H "X-Host-Pin: 1515" https://api.xv-natalia-isabela.com/horizon
```

---

## Paso 4 — Agregar variables al .env

```env
# Horizon
HORIZON_ENABLED=true
HORIZON_PREFIX=xv_natalia_horizon:
```

---

## Paso 5 — Reemplazar el worker en supervisor

Horizon gestiona sus propios workers internamente. El `xv-natalia-worker` con 5 procesos se reemplaza por un solo proceso de Horizon.

Edita `/etc/supervisor/conf.d/xv-natalia.conf`.
**Elimina** el bloque `[program:xv-natalia-worker]` y **agrega** en su lugar:

```ini
[program:xv-natalia-horizon]
process_name=%(program_name)s
command=/usr/bin/php artisan horizon
directory=/var/www/xv-natalia/be-xv-natalia
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/xv-natalia/be-xv-natalia/storage/logs/horizon.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=3600
```

El bloque `[program:xv-natalia-reverb]` se mantiene intacto.

> `stopwaitsecs=3600` le da hasta 1 hora para terminar jobs

▸ Credits: turn 1.55 • session 9.50 | Time: 1m 1s
────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
solo es para xv-natalia no para gea

• # Implementación de Laravel Horizon + Pulse — xv-natalia

## Contexto del sistema

| App | Ruta | Queue Redis DB | Reverb puerto | Supervisor actual |
|---|---|---|---|---|
| xv-natalia | `/var/www/xv-natalia/be-xv-natalia` | DB7 | 8083 | `xv-natalia-worker` (5 procs) + `xv-natalia-reverb` |

**URL base:** `https://api.xv-natalia-isabela.com`

---

## Paso 1 — Instalar Horizon

```bash
cd /var/www/xv-natalia/be-xv-natalia
sudo -u www-data composer require laravel/horizon
sudo -u www-data php artisan horizon:install
```

`horizon:install` hace tres cosas:
- Publica `config/horizon.php`
- Publica los assets del dashboard en `public/vendor/horizon`
- Registra el service provider automáticamente

**Verificar:**
```bash
ls config/horizon.php
ls public/vendor/horizon/
```

---

## Paso 2 — Configurar config/horizon.php

Abre `config/horizon.php`. Localiza la clave `environments` y reemplázala completa:

```php
'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection'   => 'redis',
            'queue'        => ['default'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'memory'       => 128,
            'tries'        => 3,
            'timeout'      => 90,
            'nice'         => 0,
        ],
    ],
    'local' => [
        'supervisor-default' => [
            'connection'   => 'redis',
            'queue'        => ['default'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'memory'       => 128,
            'tries'        => 3,
            'timeout'      => 90,
        ],
    ],
],
```

> `maxProcesses: 3` es el techo para el VPS de 1GB. Horizon arranca con 1 worker y escala solo según la carga.

---

## Paso 3 — Proteger el dashboard de Horizon

Horizon expone jobs, payloads y datos internos — hay que bloquearlo.

Edita `app/Providers/AppServiceProvider.php`. Agrega el `use` al tope del archivo y la llamada al final del método `boot()`:

```php
// Agregar este use junto a los demás imports
use Laravel\Horizon\Horizon;

// Al final de boot():
Horizon::auth(function ($request) {
    return $request->header('X-Host-Pin') === config('xv.host_pin');
});
```

> xv-natalia ya usa `X-Host-Pin` como mecanismo de autenticación en toda la app — es consistente no inventar otro sistema.

---

## Paso 4 — Variables en .env

```env
# Horizon
HORIZON_ENABLED=true
HORIZON_PREFIX=xv_natalia_horizon:
```

---

## Paso 5 — Reemplazar el worker en supervisor

Horizon gestiona sus propios workers internamente. El `xv-natalia-worker` con 5 procesos se reemplaza por un solo proceso de Horizon.

Edita `/etc/supervisor/conf.d/xv-natalia.conf`.

**Elimina** el bloque `[program:xv-natalia-worker]` y **agrega en su lugar**:

```ini
[program:xv-natalia-horizon]
process_name=%(program_name)s
command=/usr/bin/php artisan horizon
directory=/var/www/xv-natalia/be-xv-natalia
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/xv-natalia/be-xv-natalia/storage/logs/horizon.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=3600
```

> `stopwaitsecs=3600` le da hasta 1 hora para terminar jobs en curso antes de forzar el cierre. El bloque `xv-natalia-reverb` no se toca.

**Aplicar cambios:**
```bash
sudo supervisorctl stop xv-natalia-worker:*
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start xv-natalia-horizon
sudo supervisorctl status
```

**Verificar que arrancó:**
```bash
sudo supervisorctl status xv-natalia-horizon
# Resultado esperado:
# xv-natalia-horizon   RUNNING   pid XXXX, uptime 0:00:XX
```

---

## Paso 6 — Instalar Pulse

```bash
cd /var/www/xv-natalia/be-xv-natalia
sudo -u www-data composer require laravel/pulse
sudo -u www-data php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
sudo -u www-data php artisan migrate
```

`migrate` crea tres tablas en la base de datos `xv_natalia`: `pulse_entries`, `pulse_values` y `pulse_aggregates`.

**Verificar tablas:**
```bash
mysql -u root -pNuevoPassword123 xv_natalia \
-e "SHOW TABLES LIKE 'pulse%';"
```

Debe mostrar las tres tablas.

---

## Paso 7 — Configurar Pulse en .env

```env
# Pulse
PULSE_ENABLED=true
PULSE_PATH=pulse
PULSE_SERVER_SAMPLE_RATE=0.25
PULSE_SLOW_REQUEST_THRESHOLD=1000
PULSE_SLOW_JOB_THRESHOLD=1000
PULSE_SLOW_QUERY_THRESHOLD=1000
```

> `SAMPLE_RATE=0.25` registra 1 de cada 4 requests. Suficiente para ver tendencias sin presionar el VPS de 1GB.

---

## Paso 8 — Proteger el dashboard de Pulse

En `app/Providers/AppServiceProvider.php`, agrega el `use` y la llamada en `boot()`:

```php
// Agregar este use junto a los demás imports
use Laravel\Pulse\Facades\Pulse;

// Al final de boot() — junto al Horizon::auth del paso 3:
Pulse::auth(function ($request) {
    return $request->header('X-Host-Pin') === config('xv.host_pin');
});
```

El archivo `boot()` completo queda así al final:

```php
public function boot(): void
{
    // ... todo el código existente de macros ...

    // Dashboards
    Horizon::auth(function ($request) {
        return $request->header('X-Host-Pin') === config('xv.host_pin');
    });

    Pulse::auth(function ($request) {
        return $request->header('X-Host-Pin') === config('xv.host_pin');
    });
}
```

---

## Paso 9 — Exponer dashboards en nginx

Edita `/etc/nginx/sites-available/api.xv-natalia-isabela.com`.

Agrega estos dos bloques **antes** del `location ~ \.php$` existente:

```nginx
# Horizon dashboard
location ^~ /horizon {
    try_files $uri $uri/ /index.php?$query_string;
}

# Pulse dashboard
location ^~ /pulse {
    try_files $uri $uri/ /index.php?$query_string;
}
```

El bloque completo del servidor 443 debe quedar en este orden:

```nginx
location /storage/ { ... }      # existente
location ^~ /horizon { ... }    # nuevo
location ^~ /pulse { ... }      # nuevo
location / { ... }              # existente
location ~ \.php$ { ... }       # existente
location /app/ { ... }          # existente (reverb)
location ~ /\.(?!well-known) { ... }  # existente
```

**Validar y recargar:**
```bash
nginx -t && nginx -s reload
```

---

## Paso 10 — Pruebas

### Horizon

```bash
# 1. El proceso corre
sudo supervisorctl status xv-natalia-horizon

# 2. Horizon reconoce que está activo
cd /var/www/xv-natalia/be-xv-natalia
sudo -u www-data php artisan horizon:status
# Resultado esperado: Horizon is running.

# 3. Despachar job de prueba desde tinker
sudo -u www-data php artisan tinker
# Dentro de tinker:
# dispatch(function() { sleep(1); })->onQueue('default');
# exit
```

**Acceder al dashboard desde el browser:**
```
https://api.xv-natalia-isabela.com/horizon
```
Con el header `X-Host-Pin: 1515` (o el valor de `XV_HOST_PIN` en tu `.env`).

> Para pruebas rápidas desde el browser instala la extensión **ModHeader** (Chrome/Brave/Edge) y agrega el header ahí.

### Pulse

```bash
# Esperar 1-2 minutos de tráfico y verificar que se registran datos
mysql -u root -pNuevoPassword123 xv_natalia \
-e "SELECT COUNT(*) as total FROM pulse_entries;"
# Debe devolver un número > 0
```

**Acceder al dashboard:**
```
https://api.xv-natalia-isabela.com/pulse
```
Con el mismo header `X-Host-Pin`.

### Reverb (sin cambios, solo verificar)

```bash
# Proceso corriendo
sudo supervisorctl status xv-natalia-reverb

# Conexiones activas
ss -tnp | grep ':8083'

# Logs en tiempo real
tail -f /var/www/xv-natalia/be-xv-natalia/storage/logs/reverb.log
```

---

## Checklist de ejecución

- [ ] `composer require laravel/horizon` sin errores
- [ ] `config/horizon.php` publicado y editado con `maxProcesses: 3`
- [ ] `Horizon::auth()` configurado en `AppServiceProvider`
- [ ] `xv-natalia-worker` detenido en supervisor
- [ ] `xv-natalia-horizon` corriendo — `supervisorctl status` muestra `RUNNING`
- [ ] `php artisan horizon:status` responde `Horizon is running.`
- [ ] `composer require laravel/pulse` sin errores
- [ ] `php artisan migrate` crea las tres tablas `pulse_*`
- [ ] `Pulse::auth()` configurado en `AppServiceProvider`
- [ ] Variables `HORIZON_*` y `PULSE_*` agregadas al `.env`
- [ ] nginx actualizado con bloques `/horizon` y `/pulse`
- [ ] `nginx -t` sin errores
- [ ] `nginx -s reload` aplicado
- [ ] Dashboard `/horizon` accesible en browser con el header
- [ ] Dashboard `/pulse` accesible en browser con el header
- [ ] `pulse_entries` tiene registros tras 2 minutos de tráfico
- [ ] `supervisorctl status xv-natalia-reverb` sigue `RUNNING` (no se tocó)

---

## Nota de RAM

Con Horizon activo el consumo estimado del VPS:

| Proceso | RAM estimada |
|---|---|
| MySQL | ~200 MB |
| gea-wrc workers + reverb | ~250 MB |
| xv-natalia-horizon (1–3 workers) | ~60–130 MB |
| xv-natalia-reverb | ~30 MB |
| nginx + php-fpm + Redis | ~40 MB |
| **Total estimado** | **~580–650 MB** |

Si la RAM supera el 80% reduce `maxProcesses` a 2 en `config/horizon.php`.

```bash
# Monitorear en tiempo real
watch -n 5 'free -m'
```