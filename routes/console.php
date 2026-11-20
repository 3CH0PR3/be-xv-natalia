<?php

use App\Jobs\SyncCommentLikeCountersJob;
use App\Jobs\SyncMemoryReactionCountersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new SyncCommentLikeCountersJob())->everyFiveSeconds();
Schedule::job(new SyncMemoryReactionCountersJob())->everyFiveSeconds();
