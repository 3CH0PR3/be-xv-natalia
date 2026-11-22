<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('party_reactions', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
      $table->string('actor_key', 128);
      $table->string('device_id', 128)->nullable();
      $table->timestamps();

      $table->unique(['event_id', 'actor_key'], 'uq_party_reactions_event_actor');
      $table->index(['event_id', 'device_id'], 'idx_party_reactions_event_device');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('party_reactions');
  }
};
