<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryMediaItem;
use App\Models\MemoryReaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "--- RESETTING & SEEDING CLEAN SHOWCASE MEMORIES ---\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');
MemoryReaction::truncate();
MemoryComment::truncate();
MemoryMediaItem::truncate();
Memory::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

$event = Event::firstOrCreate(
  ['code' => 'XV-NATALIA'],
  [
    'honoree_name' => 'Natalia Isabella',
    'subtitle' => 'Gala de 15 Años · Recepción',
    'venue' => 'Salón Las Marías · Urb. Los Modines calle 90D #76-61',
    'party_celebrations_count' => 84,
  ]
);

$tables = EventTable::where('event_id', $event->id)->get()->keyBy('slug');

$storageBase = storage_path('app/public/memories/XV-NATALIA');
File::ensureDirectoryExists($storageBase);

$samples = [
  [
    'id' => 'mem-showcase-01',
    'tableSlug' => 'mesa-01',
    'guestName' => 'Familia Isabella',
    'ownerDeviceId' => 'dev-host-isabella-01',
    'ownerActorKey' => 'mesa-01-principal__familia-isabella',
    'momentTag' => 'Entrada Triunfal',
    'caption' => '¡Bienvenidos a la noche mágica de Natalia Isabella! Una noche inolvidable para nuestra princesa ✨👸🎂',
    'sourceFile' => dirname(__DIR__, 2) . '/src/assets/images/quinceanera_hero_gala_1794610370929.jpg',
    'destFilename' => 'quinceanera_hero_gala.jpg',
    'likes' => 18,
    'fires' => 12,
    'cheers' => 6,
    'shares' => 5,
    'timestampMs' => round(microtime(true) * 1000) - (45 * 60 * 1000), // 45 mins ago
    'comments' => [
      [
        'author' => 'Tía Carmen',
        'table' => 'Mesa 01 · Principal',
        'text' => '¡Te ves radiante Natalia! Qué orgullo de quinceañera 💕',
        'likes' => 3,
      ],
      [
        'author' => 'Familia Gómez',
        'table' => 'Mesa 04 · Esmeralda',
        'text' => '¡Felicidades a toda la familia! Hermosa recepción 🥂',
        'likes' => 1,
      ],
    ],
  ],
  [
    'id' => 'mem-showcase-02',
    'tableSlug' => 'mesa-01',
    'guestName' => 'Papá y Mamá',
    'ownerDeviceId' => 'dev-host-isabella-02',
    'ownerActorKey' => 'mesa-01-principal__papa-y-mama',
    'momentTag' => 'Vals de las Flores',
    'caption' => 'El vals más emotivo de nuestras vidas. Siempre serás nuestra pequeña princesa 🌸💃🎶',
    'sourceFile' => dirname(__DIR__, 2) . '/src/assets/images/quinceanera_toast_party_1794610371760.jpg',
    'destFilename' => 'vals_de_flores.jpg',
    'likes' => 24,
    'fires' => 15,
    'cheers' => 10,
    'shares' => 8,
    'timestampMs' => round(microtime(true) * 1000) - (30 * 60 * 1000), // 30 mins ago
    'comments' => [
      [
        'author' => 'Madrina Sofía',
        'table' => 'Mesa 02 · Orquídea',
        'text' => 'Se me salieron las lágrimas viendo a papá bailar con Natalia 🥹💐',
        'likes' => 4,
      ],
    ],
  ],
  [
    'id' => 'mem-showcase-03',
    'tableSlug' => 'mesa-02',
    'guestName' => 'Madrina Elena',
    'ownerDeviceId' => 'dev-guest-elena-03',
    'ownerActorKey' => 'mesa-02-orquidea__madrina-elena',
    'momentTag' => 'Brindis de Gala',
    'caption' => 'Por 15 años llenos de luz, bendiciones y un futuro brillante para Natalia Isabella 🥂💖✨',
    'sourceFile' => dirname(__DIR__, 2) . '/src/assets/images/quinceanera_table_qr_1794610370885.jpg',
    'destFilename' => 'brindis_gala.jpg',
    'likes' => 15,
    'fires' => 9,
    'cheers' => 4,
    'shares' => 3,
    'timestampMs' => round(microtime(true) * 1000) - (15 * 60 * 1000), // 15 mins ago
    'comments' => [
      [
        'author' => 'Tío Ricardo',
        'table' => 'Mesa 02 · Orquídea',
        'text' => '¡Salud por la quinceañera! Una velada espectacular 🥂',
        'likes' => 2,
      ],
    ],
  ],
  [
    'id' => 'mem-showcase-04',
    'tableSlug' => 'mesa-03',
    'guestName' => 'Amigas del Colegio',
    'ownerDeviceId' => 'dev-guest-amigas-04',
    'ownerActorKey' => 'mesa-03-magnolia__amigas-del-colegio',
    'momentTag' => 'Hora Loca',
    'caption' => '¡Inició la mejor fiesta del año! A darlo todo en la pista de baile con la quinceañera 🥳🎉💃',
    'sourceFile' => dirname(__DIR__, 2) . '/public/assets/aistudio/image_02186142.png',
    'destFilename' => 'hora_loca.png',
    'likes' => 31,
    'fires' => 22,
    'cheers' => 14,
    'shares' => 11,
    'timestampMs' => round(microtime(true) * 1000) - (5 * 60 * 1000), // 5 mins ago
    'comments' => [
      [
        'author' => 'Valentina',
        'table' => 'Mesa 03 · Magnolia',
        'text' => '¡El mejor vestido de la historia! Amamos las fotos 💖📸',
        'likes' => 5,
      ],
      [
        'author' => 'Camila',
        'table' => 'Mesa 03 · Magnolia',
        'text' => '¡A romper la pista! 💃🔥',
        'likes' => 3,
      ],
    ],
  ],
];

foreach ($samples as $s) {
  $table = $tables->get($s['tableSlug']) ?? $tables->first();
  $memoryDir = $storageBase . '/' . $s['id'];
  File::ensureDirectoryExists($memoryDir);
  
  $destPath = $memoryDir . '/' . $s['destFilename'];
  if (file_exists($s['sourceFile'])) {
    copy($s['sourceFile'], $destPath);
  }

  $storageRelPath = 'memories/XV-NATALIA/' . $s['id'] . '/' . $s['destFilename'];
  $mime = str_ends_with($s['destFilename'], '.png') ? 'image/png' : 'image/jpeg';
  $fileSize = file_exists($destPath) ? filesize($destPath) : 10240;

  $memory = Memory::create([
    'external_id' => $s['id'],
    'event_id' => $event->id,
    'event_table_id' => $table ? $table->id : null,
    'guest_name' => $s['guestName'],
    'table_code' => $table ? $table->code : 'Mesa 01 · Principal',
    'owner_device_id' => $s['ownerDeviceId'],
    'owner_actor_key' => $s['ownerActorKey'],
    'moment_tag' => $s['momentTag'],
    'caption' => $s['caption'],
    'media_type' => 'image',
    'status' => 'approved',
    'likes_count' => $s['likes'],
    'fires_count' => $s['fires'],
    'cheers_count' => $s['cheers'],
    'shares_count' => $s['shares'],
    'comments_count' => count($s['comments']),
    'timestamp_ms' => $s['timestampMs'],
    'created_at' => date('Y-m-d H:i:s', (int) ($s['timestampMs'] / 1000)),
    'updated_at' => date('Y-m-d H:i:s', (int) ($s['timestampMs'] / 1000)),
  ]);

  MemoryMediaItem::create([
    'external_id' => 'media-' . substr(md5($s['id']), 0, 10),
    'memory_id' => $memory->id,
    'position' => 0,
    'media_type' => 'image',
    'file_path' => $storageRelPath,
    'mime_type' => $mime,
    'file_size' => $fileSize,
    'media_blob_key' => '',
    'thumb_url' => '',
    'poster_url' => '',
  ]);

  foreach ($s['comments'] as $cIdx => $c) {
    MemoryComment::create([
      'external_id' => 'c-' . $memory->id . '-' . $cIdx,
      'memory_id' => $memory->id,
      'author' => $c['author'],
      'table_label' => $c['table'],
      'text' => $c['text'],
      'likes_count' => $c['likes'],
      'owner_device_id' => 'dev-commenter-' . $cIdx,
      'owner_actor_key' => 'mesa-commenter__' . strtolower($c['author']),
      'created_at_ms' => $s['timestampMs'] + (($cIdx + 1) * 30000),
    ]);
  }

  echo "Seeded memory: {$s['id']} ({$s['guestName']}) with photo {$s['destFilename']}\n";
}

echo "=== SHOWCASE SEED COMPLETED SUCCESSFULLY ===\n";
