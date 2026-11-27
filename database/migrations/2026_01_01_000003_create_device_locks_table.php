<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('device_locks', function (Blueprint $table): void {
      $table->uuid('id')->primary();
      $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
      $table->foreignUuid('event_table_id')->nullable()->constrained('event_tables')->nullOnDelete();
      $table->string('device_id', 128);
      $table->string('hw_fingerprint', 128)->nullable();
      $table->json('fingerprint_candidates')->nullable();
      $table->json('hardware_summary')->nullable();
      $table->string('guest_name', 80);
      $table->string('guest_name_slug', 80);
      $table->string('actor_key', 128);
      $table->string('avatar_path', 255)->nullable();
      $table->string('cover_path', 255)->nullable();
      $table->string('bio', 255)->nullable();
      $table->string('location', 100)->nullable();
      $table->timestamp('locked_at')->nullable();
      $table->timestamps();

      $table->unique(['event_id', 'device_id'], 'uq_device_locks_event_device');
      $table->unique(['event_table_id', 'guest_name_slug'], 'uq_device_locks_table_guest_slug');
      $table->index(['event_id', 'hw_fingerprint'], 'idx_device_locks_event_hwfp');
      $table->index(['event_id', 'actor_key'], 'idx_device_locks_event_actor');
      $table->index(['event_table_id', 'locked_at'], 'idx_device_locks_table_locked_at');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('device_locks');
  }
};
