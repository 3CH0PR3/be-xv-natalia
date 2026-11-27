<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('app_releases', function (Blueprint $table): void {
      $table->uuid('id')->primary();
      $table->string('version', 40)->unique('uq_app_releases_version');
      $table->unsignedBigInteger('version_order')->default(1000000);
      $table->enum('type', ['major', 'minor', 'patch'])->default('minor');
      $table->string('date_label', 60);
      $table->string('title', 280);
      $table->json('highlights');
      $table->string('commit_sha', 64)->nullable();
      $table->string('build_id', 128)->nullable();
      $table->unsignedBigInteger('applied_at_ms');
      $table->timestamps();

      $table->index('version_order', 'idx_app_releases_version_order');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('app_releases');
  }
};
