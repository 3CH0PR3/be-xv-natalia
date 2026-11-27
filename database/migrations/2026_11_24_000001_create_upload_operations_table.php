<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('upload_operations', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('operation_key', 128)->unique();
      $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
      $table->foreignUuid('event_table_id')->nullable()->constrained('event_tables')->nullOnDelete();
      $table->foreignUuid('device_lock_id')->nullable()->constrained('device_locks')->nullOnDelete();
      $table->foreignUuid('memory_id')->nullable()->constrained('memories')->nullOnDelete();
      $table->string('memory_external_id', 128)->index();
      $table->string('owner_device_id', 128)->nullable()->index();
      $table->string('owner_actor_key', 128)->nullable()->index();
      $table->string('guest_name', 120);
      $table->string('table_code', 80);
      $table->string('moment_tag', 60)->default('Fiesta en Vivo');
      $table->text('caption')->nullable();
      $table->string('request_origin', 255)->nullable();
      $table->enum('status', [
        'queued',
        'uploading',
        'processing',
        'completed',
        'completed_with_errors',
        'failed',
      ])->default('queued')->index();
      $table->unsignedInteger('total_files')->default(0);
      $table->unsignedInteger('uploaded_files')->default(0);
      $table->unsignedInteger('completed_files')->default(0);
      $table->unsignedInteger('failed_files')->default(0);
      $table->unsignedBigInteger('total_bytes')->default(0);
      $table->unsignedBigInteger('uploaded_bytes')->default(0);
      $table->text('error_summary')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('completed_at')->nullable();
      $table->timestamp('dismissed_at')->nullable()->index();
      $table->timestamps();

      $table->index(['event_id', 'owner_actor_key', 'dismissed_at'], 'upload_ops_actor_visible_idx');
      $table->index(['event_id', 'owner_device_id', 'dismissed_at'], 'upload_ops_device_visible_idx');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('upload_operations');
  }
};
