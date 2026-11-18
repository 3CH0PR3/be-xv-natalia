# Backend API RESTful Laravel 11 · XV Natalia Isabella

Arquitectura limpia modular por capas inspirada en `C:\jobs\gea-wrc\be-gea-wrc` (**Controllers -> Services -> Actions -> Models + Observers + Events + Jobs + DTOs + FormRequests + API Resources**), base de datos relacional MySQL/PostgreSQL indexada para alto rendimiento y tiempo real mediante **Laravel Reverb + Redis (Caché, Sesiones y Colas)**.

---

## 🚀 Inicio Rápido en Desarrollo

### 1. Iniciar Todo con un Solo Comando (PowerShell)
```powershell
cd C:\jobs\XV-natalia-2\backend
.\start-dev.ps1
```

O iniciar cada componente por separado:
```bash
# Terminal 1: API REST
php artisan serve --port=8000

# Terminal 2: Servidor WebSockets Reverb
php artisan reverb:start --port=8080

# Terminal 3: Procesador de Colas Redis
php artisan queue:work redis
```

### 2. Base de Datos y Seeders
```bash
php artisan migrate:fresh --seed
```

### 3. Pruebas Automatizadas
```bash
php artisan test
```

---

## 🏗️ Arquitectura por Capas

```
app/
├── Actions/                  # Lógica pura de negocio atómica y transacciones
│   ├── AppRelease/           # Sincronización de versiones de la app
│   ├── Comment/              # Comentarios y likes de comentarios
│   ├── DeviceLock/           # Check-in QR, bloqueo de mesa y huellas digitales
│   ├── Event/                # Estado del evento y celebración global
│   ├── Memory/               # Creación de álbumes, fotos, videos, reacciones y estados
│   └── Moderation/           # Métricas del evento y exportación de álbum ZIP HD
├── DTOs/                     # Objetos inmutables tipados (final readonly class)
│   ├── AppRelease/
│   ├── Comment/
│   ├── DeviceLock/
│   ├── Event/
│   ├── Memory/
│   └── Shared/
├── Events/                   # Eventos WebSockets (Laravel Reverb / ShouldBroadcastNow)
├── Exceptions/               # DomainRuleException para errores de negocio limpios
├── Http/
│   ├── Controllers/Api/V1/   # Controladores delgados con ApiResponser
│   ├── Middleware/           # VerifyHostPinMiddleware
│   ├── Requests/Api/V1/      # FormRequests de validación y toDto()
│   └── Resources/Api/V1/     # Serializadores JSON (camelCase exacto)
├── Jobs/                     # Trabajos asíncronos en cola Redis
│   ├── ProcessCommentJob.php
│   ├── ProcessLikeJob.php
│   └── ProcessMediaOptimizationJob.php
├── Models/                   # Modelos Eloquent con scopes, casts() y atributos
├── Observers/                # Invalidador de caché Redis (SCAN selective) y limpieza física
├── Services/                 # Orquestadores de casos de uso (coordinan Actions)
└── Traits/
    ├── Api/ApiResponser.php  # Trait estándar de respuestas HTTP
    └── Cache/InvalidatesCache.php # Trait para invalidación de Redis con SCAN
```

---

## 📡 Endpoints de la API (`/api/v1`)

| Método | Endpoint | Acción / Descripción |
|---|---|---|
| `GET` | `/api/v1/health` | Verificación de conectividad |
| `GET` | `/api/v1/event` | Estado global del evento y contador de fiesta |
| `POST` | `/api/v1/event/celebrate` | Alternar ¡Celebrar! (voto único por invitado) |
| `GET` | `/api/v1/tables` | Catálogo de las 6 mesas y ocupación actual |
| `POST` | `/api/v1/checkin` | Registro transaccional por escaneo de QR |
| `POST` | `/api/v1/device-locks/resolve` | Recuperar mesa por Device ID o huella hardware |
| `GET` | `/api/v1/device-locks/{deviceId}/status` | Consultar si el dispositivo fue expulsado |
| `GET` | `/api/v1/memories` | Feed paginado con filtros (`search`, `status`, `owner_device_id`) |
| `POST` | `/api/v1/memories` | Subida de álbumes (imágenes y videos HD) |
| `GET` | `/api/v1/memories/{externalId}` | Detalle de un recuerdo |
| `PATCH` | `/api/v1/memories/{externalId}/caption` | Editar dedicatoria (dueño o anfitriona) |
| `DELETE` | `/api/v1/memories/{externalId}` | Eliminar recuerdo completo |
| `DELETE` | `/api/v1/memories/{externalId}/media/{mediaId}` | Eliminar elemento multimedia específico de álbum |
| `POST` | `/api/v1/memories/{externalId}/reactions/toggle` | Reacción exclusiva (`likes`, `fires`, `cheers`) |
| `GET` | `/api/v1/memories/{externalId}/comments` | Listar comentarios de un recuerdo |
| `POST` | `/api/v1/memories/{externalId}/comments` | Publicar nuevo comentario |
| `PATCH` | `/api/v1/memories/{externalId}/comments/{commentId}` | Editar comentario (dueño) |
| `DELETE` | `/api/v1/memories/{externalId}/comments/{commentId}` | Eliminar comentario |
| `POST` | `/api/v1/memories/{externalId}/comments/{commentId}/like` | Alternar like en comentario |
| `POST` | `/api/v1/moderation/verify-pin` | Validar PIN de anfitriona (`1515`) |
| `GET` | `/api/v1/moderation/metrics` | Métricas en tiempo real (`X-Host-Pin`) |
| `PATCH` | `/api/v1/moderation/memories/{id}/status` | Cambiar estado de moderación (`X-Host-Pin`) |
| `POST` | `/api/v1/moderation/tables/expel` | Expulsar invitado y notificar por WebSocket (`X-Host-Pin`) |
| `GET` | `/api/v1/moderation/export-zip` | Descargar archivo ZIP de recuerdos aprobados (`X-Host-Pin`) |
| `GET` | `/api/v1/releases` | Historial de versiones PWA |
| `POST` | `/api/v1/releases/sync` | Sincronización de release de compilación |
