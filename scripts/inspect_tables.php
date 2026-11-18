<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (App\Models\EventTable::all() as $t) {
    echo $t->code . ' | ' . $t->name . ' | pin: ' . $t->unlock_code . ' | qr: ' . ($t->qr_code_svg ? 'HAS_SVG' : 'EMPTY') . PHP_EOL;
}
