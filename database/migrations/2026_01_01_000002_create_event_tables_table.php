<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('event_tables', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
      $table->string('slug', 40);
      $table->string('number', 10);
      $table->string('code', 80);
      $table->string('zone', 120)->nullable();
      $table->string('centerpiece', 160)->nullable();
      $table->unsignedTinyInteger('capacity')->default(10);
      $table->string('qr_token', 64)->nullable()->index();
      $table->string('qr_image_path', 255)->nullable();
      $table->string('qr_svg_path', 255)->nullable();
      $table->string('access_url', 500)->nullable();
      $table->timestamp('qr_generated_at')->nullable();
      $table->timestamps();

      $table->unique(['event_id', 'slug'], 'uq_event_tables_event_slug');
      $table->unique(['event_id', 'number'], 'uq_event_tables_event_number');
      $table->index(['event_id', 'code'], 'idx_event_tables_event_code');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('event_tables');
  }
};
