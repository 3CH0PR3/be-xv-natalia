<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('memories', function (Blueprint $table): void {
      $table->unsignedInteger('engagement_score')->default(0)->after('shares_count');
      $table->index(['engagement_score', 'created_at', 'id'], 'memories_engagement_cursor_idx');
    });

    // Backfill de registros existentes
    DB::statement('
      UPDATE memories
      SET engagement_score = likes_count + fires_count + cheers_count + comments_count
    ');
  }

  public function down(): void
  {
    Schema::table('memories', function (Blueprint $table): void {
      $table->dropIndex('memories_engagement_cursor_idx');
      $table->dropColumn('engagement_score');
    });
  }
};
