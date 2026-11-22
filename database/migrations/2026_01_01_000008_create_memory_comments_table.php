<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('memory_comments', function (Blueprint $table): void {
      $table->id();
      $table->string('external_id', 128)->unique('uq_memory_comments_external_id');
      $table->foreignId('memory_id')->constrained('memories')->cascadeOnDelete();
      $table->foreignId('device_lock_id')->nullable()->constrained('device_locks')->nullOnDelete();
      $table->string('author', 80);
      $table->string('table_label', 80);
      $table->string('text', 500);
      $table->string('owner_device_id', 128)->nullable();
      $table->string('owner_actor_key', 128)->nullable();
      $table->unsignedInteger('likes_count')->default(0);
      $table->unsignedBigInteger('created_at_ms');
      $table->timestamps();

      $table->index(['memory_id', 'created_at_ms'], 'idx_comments_memory_created');
      $table->index('device_lock_id', 'idx_comments_device_lock');
      $table->index('owner_device_id', 'idx_comments_owner_device');
      $table->index('owner_actor_key', 'idx_comments_owner_actor');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('memory_comments');
  }
};
