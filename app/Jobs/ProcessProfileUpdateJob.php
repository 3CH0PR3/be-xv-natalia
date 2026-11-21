<?php

namespace App\Jobs;

use App\Events\ProfileUpdated;
use App\Models\DeviceLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProfileUpdateJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public string $eventCode,
    public array $profileData
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    try {
      broadcast(new ProfileUpdated($this->eventCode, $this->profileData))->toOthers();
    } catch (\Throwable $e) {
      Log::warning('ProcessProfileUpdateJob broadcast warning: ' . $e->getMessage());
    }
  }
}
