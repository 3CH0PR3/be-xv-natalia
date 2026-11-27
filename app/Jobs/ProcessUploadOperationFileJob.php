<?php

namespace App\Jobs;

use App\Actions\UploadOperation\MarkOperationFileFailedAction;
use App\Actions\UploadOperation\ProcessUploadOperationFileAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUploadOperationFileJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 2;
  public int $timeout = 600;

  public function __construct(
    public int|string $uploadOperationFileId,
    string $queueName = 'media-fast'
  ) {
    $this->queue = $queueName;
  }

  public function handle(ProcessUploadOperationFileAction $action): void
  {
    $action->execute($this->uploadOperationFileId);
  }

  public function failed(\Throwable $exception): void
  {
    Log::error('[UploadOperation] ProcessUploadOperationFileJob failed', [
      'upload_operation_file_id' => $this->uploadOperationFileId,
      'message' => $exception->getMessage(),
    ]);

    try {
      app(ProcessUploadOperationFileAction::class)->cleanupFailedStaging($this->uploadOperationFileId);
    } catch (\Throwable) {
    }

    try {
      app(MarkOperationFileFailedAction::class)->executeById(
        $this->uploadOperationFileId,
        'PROCESSING_FAILED',
        $exception->getMessage() ?: 'Error procesando el archivo en segundo plano.'
      );
    } catch (\Throwable) {
    }
  }
}
