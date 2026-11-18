<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('memories', function (Blueprint $table): void {
      $table->id();
      $table->string('external_id', 128)->unique('uq_memories_external_id');
      $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
      $table->foreignId('event_table_id')->nullable()->constrained('event_tables')->nullOnDelete();
      $table->string('guest_name', 80);
      $table->string('table_code', 80);
      $table->string('owner_device_id', 128)->nullable();
      $table->string('owner_actor_key', 128)->nullable();
      $table->string('moment_tag', 60)->default('Fiesta en Vivo');
      $table->string('caption', 600)->nullable();
      $table->enum('media_type', ['image', 'video'])->default('image');
      $table->enum('status', ['approved', 'pending', 'rejected', 'hidden'])->default('approved');
      $table->unsignedInteger('likes_count')->default(0);
      $table->unsignedInteger('fires_count')->default(0);
      $table->unsignedInteger('cheers_count')->default(0);
      $table->unsignedInteger('comments_count')->default(0);
      $table->unsignedInteger('shares_count')->default(0);
      $table->unsignedBigInteger('timestamp_ms');
      $table->timestamps();

      $table->index(['event_id', 'status', 'timestamp_ms'], 'idx_memories_feed');
      $table->index(['event_id', 'owner_device_id', 'timestamp_ms'], 'idx_memories_owner_device');
      $table->index(['event_id', 'owner_actor_key', 'timestamp_ms'], 'idx_memories_owner_actor');
      $table->index(['event_id', 'table_code', 'timestamp_ms'], 'idx_memories_table_code');
      $table->index(['event_id', 'media_type', 'status', 'timestamp_ms'], 'idx_memories_media_type');
      $table->index(['event_id', 'comments_count', 'timestamp_ms'], 'idx_memories_with_comments');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('memories');
  }
};
