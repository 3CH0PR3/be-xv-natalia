<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DeviceLock;
use App\Models\DeviceFingerprintIndex;
use App\Models\MemoryComment;
use App\Models\MemoryCommentLike;
use App\Models\MemoryReaction;
use App\Models\PartyReaction;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DeviceFingerprintIndex::truncate();
DeviceLock::truncate();
MemoryCommentLike::truncate();
MemoryComment::truncate();
MemoryReaction::truncate();
PartyReaction::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

Event::query()->update(['party_celebrations_count' => 0]);
Cache::store('redis')->flush();

echo "Database cleaned and ready for k6 100-user test.\n";
