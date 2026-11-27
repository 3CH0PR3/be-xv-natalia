<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('memory_reactions', function (Blueprint $table): void {
      $table->uuid('id')->primary();
      $table->foreignUuid('memory_id')->constrained('memories')->cascadeOnDelete();
      $table->string('actor_key', 128);
      $table->string('device_id', 128)->nullable();
      $table->enum('choice', ['likes', 'fires', 'cheers']);
      $table->timestamps();

      $table->unique(['memory_id', 'actor_key'], 'uq_memory_reactions_memory_actor');
      $table->index(['memory_id', 'device_id', 'choice'], 'idx_memory_reactions_device_choice');
      $table->index(['memory_id', 'choice'], 'idx_memory_reactions_choice');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('memory_reactions');
  }
};
