<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('device_fingerprint_index', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
      $table->foreignId('device_lock_id')->constrained('device_locks')->cascadeOnDelete();
      $table->string('fingerprint', 128);
      $table->timestamps();

      $table->unique(['event_id', 'fingerprint'], 'uq_dev_fp_event_fingerprint');
      $table->index('device_lock_id', 'idx_dev_fp_lock');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('device_fingerprint_index');
  }
};
