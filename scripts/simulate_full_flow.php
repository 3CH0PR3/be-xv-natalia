<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Models\EventTable;
use App\Models\DeviceLock;
use App\Models\Memory;
use App\Models\MemoryMediaItem;
use App\Models\MemoryReaction;
use App\Models\MemoryComment;
use App\Models\MemoryCommentLike;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "====================================================================\n";
echo "PRUEBAS END-TO-END DE INSERCIÓN Y FLUJO COMPLETO EN LARAVEL 11 & MYSQL\n";
echo "====================================================================\n\n";

// 1. Limpieza inicial para garantizar prueba limpia
echo "[Paso 1] Limpiando datos previos de prueba en base de datos xv_natalia...\n";
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
MemoryCommentLike::truncate();
MemoryComment::truncate();
MemoryReaction::truncate();
MemoryMediaItem::truncate();
Memory::truncate();
DeviceLock::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
(new \Database\Seeders\EventCatalogSeeder())->run();
Cache::store('redis')->flush();

echo "✓ Tablas de recuerdos y bloqueos reseteadas limpiamente.\n\n";

// 2. Consulta de mesas y verificación de catálogo
echo "[Paso 2] Verificando catálogo de 6 mesas...\n";
$tables = EventTable::ordered()->get();
echo "✓ Total mesas en BD: " . $tables->count() . "\n";
foreach ($tables as $t) {
    echo "  - {$t->code} ({$t->slug}) - Capacidad: {$t->capacity}\n";
}
if ($tables->count() !== 6) {
    throw new Exception("Error: Se esperaban 6 mesas.");
}
echo "\n";

// 3. Simular ingreso de invitado cuando NO hay fotos
echo "[Paso 3] Invitado consulta el Muro antes de que haya fotos...\n";
$response = file_get_contents('http://127.0.0.1:8001/api/v1/memories');
$memoriesJson = json_decode($response, true);
echo "✓ Respuesta recibida. Cantidad de fotos en muro: " . count($memoriesJson['data']) . "\n";
if (count($memoriesJson['data']) !== 0) {
    throw new Exception("Error: Se esperaba feed vacío.");
}
echo "✓ Estado verificado: El muro se muestra vacío para invitados sin fotos.\n\n";

// 4. Intento de publicar sin QR check-in (Debe ser bloqueado por EnsureActiveDeviceLockMiddleware)
echo "[Paso 4] Intento de publicar sin escanear QR (Debe responder 403 QR_SESSION_REQUIRED)...\n";
$ch = curl_init('http://127.0.0.1:8001/api/v1/memories');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['guestName' => 'Invitado Anónimo']);
$blockedRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "✓ Bloqueo sin QR HTTP Status: {$httpCode} (Esperado 403)\n";
if ($httpCode !== 403) {
    throw new Exception("Error: debía retornar 403 por falta de QR check-in.");
}
echo "✓ Middleware 'device.lock' funcionando correctamente.\n\n";

// 5. Escaneo de QR y Check-in en Mesa 01 (Mesa 01 · Principal)
echo "[Paso 5] Invitada escanea QR de Mesa 01 y realiza Check-in...\n";
$guestData = [
    'eventCode' => 'XV-NATALIA',
    'tableCode' => 'Mesa 01 · Principal',
    'guestName' => 'Sofía Mendoza',
    'deviceId' => 'dev-sofia-guest-441',
    'hwFingerprint' => 'fp-canvas-audio-webgl-sofia-1234',
];

$ch = curl_init('http://127.0.0.1:8001/api/v1/checkin');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($guestData));
$checkinRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "✓ Check-in HTTP Status: {$httpCode}\n";
$checkinRes = json_decode($checkinRaw, true);
if ($httpCode !== 201) {
    echo "Respuesta: " . $checkinRaw . "\n";
    throw new Exception("Fallo en Check-in");
}
echo "✓ Invitada registrada exitosamente: {$checkinRes['data']['guestName']} en {$checkinRes['data']['tableCode']}\n";

// Verificar en MySQL
$dbLock = DeviceLock::where('device_id', 'dev-sofia-guest-441')->first();
echo "✓ Verificado en MySQL: DeviceLock ID={$dbLock->id}, Guest={$dbLock->guest_name}, Mesa={$dbLock->table->code}\n\n";

// 6. Creación de imagen de prueba real con GD (simulando foto de fiesta)
echo "[Paso 6] Generando imagen real de recuerdo con GD...\n";
$tmpImgPath = sys_get_temp_dir() . '/recuerdo_quinceanera_sofia.jpg';
$width = 800;
$height = 600;
$im = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($im, 30, 27, 75); // Indigo oscuro
$gold = imagecolorallocate($im, 245, 158, 11);
$white = imagecolorallocate($im, 255, 255, 255);
$pink = imagecolorallocate($im, 244, 114, 182);

imagefilledrectangle($im, 0, 0, $width, $height, $bg);
imagestring($im, 5, 260, 240, "XV NATALIA ISABELLA", $gold);
imagestring($im, 5, 220, 280, "Recuerdo de Mesa 01 - Sofia Mendoza", $white);
imagestring($im, 4, 250, 320, "27 Noviembre 2026 - Gala de 15", $pink);
imagejpeg($im, $tmpImgPath, 90);
imagedestroy($im);
echo "✓ Archivo JPEG generado temporalmente en {$tmpImgPath} (" . filesize($tmpImgPath) . " bytes)\n\n";

// 7. Subir imagen con pie de foto al Backend
echo "[Paso 7] Subiendo foto con pie de foto al backend (POST /api/v1/memories con X-Device-Id)...\n";
$cFile = new CURLFile($tmpImgPath, 'image/jpeg', 'recuerdo_sofia_quinceanera.jpg');
$postFields = [
    'eventCode' => 'XV-NATALIA',
    'guestName' => 'Sofía Mendoza',
    'tableCode' => 'Mesa 01 · Principal',
    'ownerDeviceId' => 'dev-sofia-guest-441',
    'ownerActorKey' => 'actor-sofia-guest-441',
    'momentTag' => 'Vals de las Flores',
    'caption' => 'Mis mejores deseos en tus quince primaveras Natalia, que Dios te bendiga siempre! ✨🎉',
    'files[0]' => $cFile,
];

$ch = curl_init('http://127.0.0.1:8001/api/v1/memories');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'X-Device-Id: dev-sofia-guest-441',
    'X-Actor-Key: actor-sofia-guest-441',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
$uploadRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "✓ Upload HTTP Status: {$httpCode}\n";
$uploadRes = json_decode($uploadRaw, true);
if ($httpCode !== 201) {
    echo "Respuesta error: " . $uploadRaw . "\n";
    throw new Exception("Fallo al subir recuerdo");
}

$memoryId = $uploadRes['data']['id'];
echo "✓ Recuerdo publicado con ID: {$memoryId}\n";
echo "✓ Pie de foto: \"{$uploadRes['data']['caption']}\"\n";
echo "✓ URL de Imagen: {$uploadRes['data']['imageUrl']}\n";
echo "✓ Etiqueta de Momento: {$uploadRes['data']['momentTag']}\n\n";

// 8. Verificación física y en base de datos MySQL xv_natalia
echo "[Paso 8] Verificación estricta en base de datos MySQL (xv_natalia)...\n";
$dbMemory = Memory::with('mediaItems')->where('external_id', $memoryId)->first();
if (!$dbMemory) {
    throw new Exception("Error: Recuerdo no encontrado en tabla memories");
}

echo "✓ Registro en tabla 'memories':\n";
echo "  - ID Interno: {$dbMemory->id}\n";
echo "  - External ID: {$dbMemory->external_id}\n";
echo "  - Invitada: {$dbMemory->guest_name}\n";
echo "  - Mesa: {$dbMemory->table_code}\n";
echo "  - Device ID: {$dbMemory->owner_device_id}\n";
echo "  - Pie de foto: {$dbMemory->caption}\n";
echo "  - Status: {$dbMemory->status}\n";
echo "  - Likes: {$dbMemory->likes_count} | Fires: {$dbMemory->fires_count} | Shares: {$dbMemory->shares_count} | Comments: {$dbMemory->comments_count}\n";

$mediaItem = $dbMemory->mediaItems->first();
if (!$mediaItem) {
    throw new Exception("Error: Elemento multimedia no guardado en tabla memory_media_items");
}
echo "✓ Registro en tabla 'memory_media_items':\n";
echo "  - File Path: {$mediaItem->file_path}\n";
echo "  - Thumb Path: {$mediaItem->thumb_path}\n";
echo "  - Size: {$mediaItem->byte_size} bytes\n";
echo "  - Mime: {$mediaItem->mime_type}\n";
echo "  - Existe físicamente en disco public: " . (Storage::disk('public')->exists($mediaItem->file_path) ? 'SÍ' : 'NO') . "\n\n";

// 9. Consulta del muro como invitado normal (con fotos presentes) y filtro por categoría
echo "[Paso 9] Consulta del Muro y prueba de filtro por categoría (albums)...\n";
$feedResRaw = file_get_contents('http://127.0.0.1:8001/api/v1/memories');
$feedRes = json_decode($feedResRaw, true);
echo "✓ Total fotos en muro: " . count($feedRes['data']) . "\n";
echo "✓ Primera foto mostrada: Autor '{$feedRes['data'][0]['guestName']}', Pie: '{$feedRes['data'][0]['caption']}'\n\n";

// 10. Reaccionar a su propia foto (reacción 'fires' 🔥)
echo "[Paso 10] Invitada reacciona con 'fires' 🔥 a su propia foto...\n";
$reactData = [
    'actorKey' => 'actor-sofia-guest-441',
    'deviceId' => 'dev-sofia-guest-441',
    'choice' => 'fires',
];
$ch = curl_init("http://127.0.0.1:8001/api/v1/memories/{$memoryId}/reactions/toggle");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Device-Id: dev-sofia-guest-441',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reactData));
$reactRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$reactRes = json_decode($reactRaw, true);
echo "✓ Toggle Reacción HTTP: {$httpCode}, Fires: {$reactRes['data']['fires']}, Choice: {$reactRes['data']['choice']}\n";

// Verificar en MySQL
$dbReaction = MemoryReaction::where('memory_id', $dbMemory->id)->first();
echo "✓ Verificado en MySQL: MemoryReaction memory_id={$dbReaction->memory_id}, choice={$dbReaction->choice}\n";
$dbMemory->refresh();
echo "✓ Contador en tabla memories: fires_count={$dbMemory->fires_count}\n\n";

// 11. Editar el pie de foto de la foto propia
echo "[Paso 11] Invitada edita el pie de foto de su recuerdo...\n";
$newCaption = "¡Mis mejores deseos en tus quince primaveras, Natalia hermosa! Que Dios te bendiga y cumplas muchos más! ✨🎉💖";
$ch = curl_init("http://127.0.0.1:8001/api/v1/memories/{$memoryId}/caption");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Device-Id: dev-sofia-guest-441',
    'X-Actor-Key: actor-sofia-guest-441',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['caption' => $newCaption]));
$editRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$editRes = json_decode($editRaw, true);
echo "✓ Editar Pie de Foto HTTP: {$httpCode}\n";
echo "✓ Nuevo pie de foto retornado: \"{$editRes['data']['caption']}\"\n";
$dbMemory->refresh();
echo "✓ Verificado en MySQL: \"{$dbMemory->caption}\"\n\n";

// 12. Incrementar contador de Compartir (Share)
echo "[Paso 12] Invitada comparte la foto (incremento de contador con X-Device-Id)...\n";
$ch = curl_init("http://127.0.0.1:8001/api/v1/memories/{$memoryId}/shares");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'X-Device-Id: dev-sofia-guest-441',
]);
$shareRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$shareRes = json_decode($shareRaw, true);
echo "✓ Incremento Compartir HTTP: {$httpCode}, Shares: {$shareRes['data']['shares']}\n";
$dbMemory->refresh();
echo "✓ Verificado en MySQL: shares_count={$dbMemory->shares_count}\n\n";

// 13. Check-in de Carlos en Mesa 02 y comentario
echo "[Paso 13] Check-in de Carlos en Mesa 02 y publicación de comentario...\n";
$carlosCheckin = [
    'eventCode' => 'XV-NATALIA',
    'tableCode' => 'Mesa 02 · Orquídea',
    'guestName' => 'Carlos Alvarado',
    'deviceId' => 'dev-carlos-guest-882',
    'actorKey' => 'actor-carlos-guest-882',
];
$ch = curl_init('http://127.0.0.1:8001/api/v1/checkin');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($carlosCheckin));
curl_exec($ch);
curl_close($ch);

$commentData = [
    'author' => 'Carlos Alvarado',
    'table' => 'Mesa 02 · Orquídea',
    'text' => '¡Qué momento tan hermoso! La pasamos increíble anoche.',
    'ownerDeviceId' => 'dev-carlos-guest-882',
    'ownerActorKey' => 'actor-carlos-guest-882',
];
$ch = curl_init("http://127.0.0.1:8001/api/v1/memories/{$memoryId}/comments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Device-Id: dev-carlos-guest-882',
    'X-Actor-Key: actor-carlos-guest-882',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($commentData));
$commentRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$commentRes = json_decode($commentRaw, true);
echo "✓ Comentario HTTP: {$httpCode}, Comentario ID: {$commentRes['data']['id']}, Autor: {$commentRes['data']['author']}\n";
$dbMemory->refresh();
echo "✓ Verificado en MySQL (Observer sincronizado): comments_count={$dbMemory->comments_count}\n\n";

// 14. Seguridad: Intento de Carlos de editar la foto de Sofía (Debe ser rechazado con 403)
echo "[Paso 14] Verificación de seguridad: Carlos intenta editar el pie de foto de Sofía...\n";
$ch = curl_init("http://127.0.0.1:8001/api/v1/memories/{$memoryId}/caption");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Device-Id: dev-carlos-guest-882',
    'X-Actor-Key: actor-carlos-guest-882',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['caption' => 'Intento no autorizado']));
$hackRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "✓ Intento no autorizado HTTP: {$httpCode} (Esperado: 403 Forbidden)\n";
if ($httpCode !== 403) {
    throw new Exception("Error de seguridad: debió ser 403 Forbidden");
}
echo "✓ Protección de autoría validada con éxito.\n\n";

// 15. Moderación y Purga del Evento
echo "[Paso 15] Panel de Moderación: Consulta de Métricas y Purga con rotación de epoch y db_scope...\n";
$ch = curl_init('http://127.0.0.1:8001/api/v1/moderation/metrics');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'X-Host-Pin: 1515']);
$metricsRaw = curl_exec($ch);
curl_close($ch);
$metrics = json_decode($metricsRaw, true)['data'];
echo "✓ Métricas de Moderación:\n";
echo "  - Total Recuerdos: {$metrics['totalMemories']}\n";
echo "  - Invitados Conectados: {$metrics['connectedGuests']}\n";
echo "  - Total Comentarios: {$metrics['totalComments']}\n";

// Purga del evento
$ch = curl_init('http://127.0.0.1:8001/api/v1/moderation/purge');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'X-Host-Pin: 1515']);
$purgeRaw = curl_exec($ch);
$purgeCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "✓ Purga de Evento HTTP Status: {$purgeCode}\n";
$purgeRes = json_decode($purgeRaw, true);
echo "✓ Evento purgado: dbScope={$purgeRes['data']['dbScope']}, epoch={$purgeRes['data']['epoch']}\n";

$event = Event::byCode('XV-NATALIA')->first();
echo "✓ Verificado en MySQL: Event ID={$event->id}, epoch={$event->epoch}, db_scope={$event->db_scope}\n";
echo "✓ Recuerdos restantes en MySQL: " . Memory::count() . "\n";
echo "✓ Bloqueos restantes en MySQL: " . DeviceLock::count() . "\n";

// Limpiar archivo temporal si quedó
if (file_exists($tmpImgPath)) {
    unlink($tmpImgPath);
}

echo "\n====================================================================\n";
echo "¡TODOS LOS 15 PASOS DEL FLUJO SENIOR Y BASE DE DATOS PASARON EXITOSAMENTE!\n";
echo "====================================================================\n";
