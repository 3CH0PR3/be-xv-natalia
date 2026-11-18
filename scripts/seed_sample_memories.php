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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$event = Event::firstOrCreate(
    ['code' => 'XV-NATALIA'],
    [
        'honoree_name' => 'Natalia Isabella',
        'subtitle' => 'Gala de 15 Años · Recepción',
        'venue' => 'Salón Las Marías · Urb. Los Modines calle 90D #76-61',
        'party_celebrations_count' => 0,
        'db_scope' => 'laravel-v1',
        'epoch' => 1,
    ]
);

$table = EventTable::where('event_id', $event->id)->first();
if (!$table) {
    (new \Database\Seeders\EventCatalogSeeder())->run();
    $table = EventTable::where('event_id', $event->id)->first();
}

// Checkin a guest for initial memories
$lock = DeviceLock::updateOrCreate(
    ['event_id' => $event->id, 'device_id' => 'host-seed-device'],
    [
        'event_table_id' => $table->id,
        'guest_name' => 'Familia Isabella',
        'guest_name_slug' => 'familia-isabella',
        'actor_key' => 'mesa-01-principal__familia-isabella',
        'hw_fingerprint' => 'hw-host-seed',
        'locked_at' => now(),
    ]
);

// Create 3 memories if none exist
if (Memory::forEvent($event->id)->count() < 3) {
    $samples = [
        [
            'caption' => '¡Bienvenidos a la noche mágica de Natalia Isabella! ✨🎂',
            'moment' => 'Entrada Triunfal',
            'guest' => 'Familia Isabella',
        ],
        [
            'caption' => 'Hermoso el vals de las flores con su padre 🌸🎶',
            'moment' => 'Vals de las Flores',
            'guest' => 'Madrina Elena',
        ],
        [
            'caption' => '¡Que empiece la fiesta y el baile! 🥳🥂',
            'moment' => 'Hora Loca',
            'guest' => 'Primos y Amigos',
        ],
    ];

    foreach ($samples as $i => $s) {
        $extId = 'mem-seed-' . time() . '-' . Str::random(5);
        $fileName = "seed-img-{$i}.jpg";
        $storagePath = "memories/{$event->code}/{$extId}/{$fileName}";

        // Simple 200x200 jpeg
        $im = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($im, 240 - ($i * 20), 180 + ($i * 10), 220);
        imagefilledrectangle($im, 0, 0, 200, 200, $bg);
        ob_start();
        imagejpeg($im, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($im);

        Storage::disk('public')->put($storagePath, $bytes);

        $memory = Memory::create([
            'event_id' => $event->id,
            'event_table_id' => $table->id,
            'external_id' => $extId,
            'table_code' => $table->code,
            'guest_name' => $s['guest'],
            'owner_device_id' => 'host-seed-device',
            'owner_actor_key' => 'mesa-01-principal__familia-isabella',
            'caption' => $s['caption'],
            'status' => 'approved',
            'media_type' => 'image',
            'moment_tag' => $s['moment'],
            'timestamp_ms' => (int) (microtime(true) * 1000) - ($i * 60000),
        ]);

        MemoryMediaItem::create([
            'external_id' => 'media-' . Str::random(10),
            'memory_id' => $memory->id,
            'file_path' => $storagePath,
            'thumbnail_path' => '',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'sort_order' => 0,
        ]);
    }
}

echo "Memories in DB: " . Memory::forEvent($event->id)->count() . "\n";
