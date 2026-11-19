<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('memory_shares', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('memory_id')->constrained('memories')->cascadeOnDelete();
      $table->foreignId('device_lock_id')->nullable()->constrained('device_locks')->nullOnDelete();
      $table->string('actor_key', 128);
      $table->string('device_id', 128)->nullable();
      $table->timestamps();

      $table->index(['memory_id', 'actor_key'], 'idx_memory_shares_memory_actor');
      $table->index(['memory_id', 'device_id'], 'idx_memory_shares_memory_device');
      $table->index(['memory_id', 'created_at'], 'idx_memory_shares_memory_created');
      $table->index('device_lock_id', 'idx_memory_shares_device_lock');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('memory_shares');
  }
};
