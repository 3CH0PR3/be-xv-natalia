<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('upload_operation_files', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->foreignUuid('upload_operation_id')->constrained('upload_operations')->cascadeOnDelete();
      $table->string('file_key', 128);
      $table->foreignUuid('memory_media_item_id')->nullable()->constrained('memory_media_items')->nullOnDelete();
      $table->string('original_name', 255);
      $table->string('mime_type', 120)->default('application/octet-stream');
      $table->enum('media_type', ['image', 'video'])->default('image');
      $table->unsignedBigInteger('byte_size')->default(0);
      $table->unsignedBigInteger('uploaded_bytes')->default(0);
      $table->unsignedInteger('chunk_size')->default(4194304);
      $table->unsignedInteger('total_chunks')->default(1);
      $table->json('received_chunks')->nullable();
      $table->unsignedInteger('sort_order')->default(0);
      $table->enum('status', [
        'queued_upload',
        'uploading',
        'uploaded',
        'processing',
        'completed',
        'failed',
      ])->default('queued_upload')->index();
      $table->string('disk', 32)->nullable();
      $table->string('temp_dir', 255)->nullable();
      $table->string('poster_path', 255)->nullable();
      $table->string('final_path', 255)->nullable();
      $table->string('error_code', 64)->nullable();
      $table->text('error_message')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('uploaded_at')->nullable();
      $table->timestamp('processed_at')->nullable();
      $table->timestamps();

      $table->unique(['upload_operation_id', 'file_key'], 'upload_op_files_unique_key');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('upload_operation_files');
  }
};
