# Reglas de Arquitectura Backend (Laravel)

## 0. Formato
- 2 tabs de indentación en todos los archivos PHP. Prohibido 4 espacios.
- Clases `final readonly` en DTOs, Actions y Services.
- Prohibido `dd`, `dump`, `var_dump`, `Log::debug` y código muerto.

## 1. Migraciones (Local Dev)
- PROHIBIDO crear migraciones incrementales (`add_*`, `fix_*`, `update_*`).
- Toda columna, índice o restricción nueva se agrega directamente en el `create_*_table.php` original.
- Al cambiar el esquema, se eliminan las migraciones temporales y se ejecuta `php artisan migrate:fresh`.

## 2. Flujo Unidireccional
```
Route → Middleware → FormRequest → Controller → DTO → Service → Action → Models → Resource → ApiResponser
```
Ninguna capa salta a otra ni retrocede.

## 3. Responsabilidades por Capa

### Route (`routes/*`)
- Declara endpoints, métodos HTTP y middlewares.
- Sin lógica.

### Middleware (`app/Http/Middleware/*`)
- Extrae, normaliza e inyecta el contexto de la petición (headers, identificadores, sesión).
- Expone el contexto mediante métodos de la petición (`$request->deviceId()`) para que ninguna capa posterior lo repita.
- Valida sesión o autorización.

### FormRequest (`app/Http/Requests/*`)
- Solo valida tipos e integridad de la entrada.
- `authorize()` retorna `true` cuando la autorización la maneja un middleware.
- Prohibido lógica de negocio y queries.

### Controller (`app/Http/Controllers/*`)
- Delgado: máx. ~40 líneas por clase.
- Inyecta únicamente el Service.
- Flujo por método: recibe FormRequest → crea el DTO con `Dto::fromArray($request->validated())` → llama al Service → responde con `ApiResponser`.
- Prohibido: `$request->validate()`, `$request->header()`, queries, `try/catch`, lógica de negocio, armar Resources.

### DTO (`app/DTOs/*`)
- `final readonly class` con propiedades públicas tipadas y nullable.
- Usa el trait `HasSafeArray` y construye con `fromArray(array $data): self`.
- Todo valor se lee con `self::get($data, 'clave')`. Prohibido `?? null` suelto o `$data['clave']` directo.
- Sin lógica de negocio. Solo transporta datos y, si hace falta, helpers de lectura (`auditFilters()`).

```php
<?php

namespace App\DTOs\<Dominio>;

use App\DTOs\Shared\HasSafeArray;

final readonly class XData
{
  use HasSafeArray;

  public function __construct(
    public ?int    $id,
    public ?string $name,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      id:   self::get($data, 'id'),
      name: self::get($data, 'name'),
    );
  }
}
```

```php
<?php

namespace App\DTOs\Shared;

trait HasSafeArray
{
  protected static function get(array $data, string $key): mixed
  {
    return $data[$key] ?? null;
  }
}
```

### Service (`app/Services/*`)
- Orquestador puro. Solo inyecta Actions, las llama con el DTO y envuelve el resultado en Resources.
- Retorna un `array` con las claves que consume el Controller (`['item' => XResource::make(...)]`, `['paginated' => ..., 'items' => XResource::collection(...)]`).
- Prohibido: queries, filtros, ordenamientos, `if` de negocio, mapeo manual de arrays, `DB::`, firmas polimórficas (`DTO|string|null`) y parámetros sueltos cuando existe un DTO.
- Si necesita validar un permiso o regla de negocio, esa regla va en una Action.

### Action (`app/Actions/*`)
- `final readonly class` de responsabilidad única con un método público `execute(...)`.
- Contiene TODA la lógica de negocio: queries Eloquent, filtros, ordenamientos, paginación, reglas, validaciones de dominio y `DB::transaction()` en escrituras.
- Recibe DTO o primitivos tipados. Retorna Model, Collection, Paginator o valor tipado, nunca JSON ni Resources.
- Sin acceso a `Request`, headers ni `ApiResponser`.
- Métodos auxiliares `private` para dividir pasos (`applySearch`, `applyOrdering`).
- Una Action no llama a otra Action; si necesita coordinar varias, lo hace el Service.

### Models (`app/Models/*`)
- Solo relaciones, scopes, casts, accessors y reglas de persistencia.
- Sin lógica de casos de uso.

### Resource (`app/Http/Resources/*`)
- Transforma Models o estructuras en arrays JSON consistentes.
- Todo mapeo de salida vive aquí. Prohibido en Controller, Service o Action.

### ApiResponser (`app/Traits/Api/ApiResponser.php`)
- Único formateador de respuestas: `sendResponse()`, `sendPaginatedResponse()`, `sendError()`.
- Estructura fija: `{ success, code, data, message }`.

## 4. Seguridad y Consultas
- Prohibido interpolar valores del usuario en SQL, incluido `orderByRaw` y `whereRaw`. Usar siempre bindings (`?`).
- Prohibido `addslashes` como sanitización.
- Escrituras multi-paso siempre dentro de `DB::transaction()` en la Action.

## 5. Nomenclatura
- Actions: verbo + entidad (`CreateX`, `UpdateX`, `ListXAction`), método `execute`.
- DTOs: `XData`.
- Services: `XService`.
- FormRequests: `XStoreRequest`, `XUpdateRequest`, `XIndexRequest`.
- Resources: `XResource`, `XListItemResource`.

## 6. Verificación y Git
- Ejecuta `php -l` sobre cada archivo PHP modificado y reporta el resultado real.
- PROHIBIDO `commit`, `push`, `add`, `stash`, `reset`, `checkout` o cambiar de rama sin permiso explícito en ese mensaje.
- Al finalizar, pregunta una sola vez si desea hacer commit listando los archivos modificados.

## 7. Checklist de Auditoría
- [ ] 2 tabs, sin 4 espacios.
- [ ] Sin migraciones `add_*`, `fix_*`, `update_*`.
- [ ] Controller: solo FormRequest, DTO, Service, `ApiResponser`.
- [ ] Controller sin headers manuales, sin `validate()`, sin queries.
- [ ] DTO `final readonly` con `HasSafeArray`, `fromArray` y `self::get()`.
- [ ] Service sin queries, sin `if` de negocio, sin mapeo manual, sin firmas polimórficas.
- [ ] Action con toda la lógica y `DB::transaction()` en escrituras.
- [ ] Resources como única capa de mapeo de salida.
- [ ] Sin SQL con valores interpolados.
- [ ] `php -l` ejecutado sin errores.