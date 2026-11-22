<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('memory_comment_likes', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('comment_id')->constrained('memory_comments')->cascadeOnDelete();
      $table->string('actor_key', 128);
      $table->string('device_id', 128)->nullable();
      $table->timestamps();

      $table->unique(['comment_id', 'actor_key'], 'uq_comment_likes_comment_actor');
      $table->index(['comment_id', 'device_id'], 'idx_comment_likes_comment_device');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('memory_comment_likes');
  }
};
