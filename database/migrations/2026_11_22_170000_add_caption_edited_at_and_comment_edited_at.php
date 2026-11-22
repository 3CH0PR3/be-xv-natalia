<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('memories', function (Blueprint $table): void {
      if (!Schema::hasColumn('memories', 'caption_edited_at')) {
        $table->timestamp('caption_edited_at')->nullable()->after('caption');
      }
    });

    Schema::table('memory_comments', function (Blueprint $table): void {
      if (!Schema::hasColumn('memory_comments', 'edited_at')) {
        $table->timestamp('edited_at')->nullable()->after('text');
      }
    });
  }

  public function down(): void
  {
    Schema::table('memories', function (Blueprint $table): void {
      if (Schema::hasColumn('memories', 'caption_edited_at')) {
        $table->dropColumn('caption_edited_at');
      }
    });

    Schema::table('memory_comments', function (Blueprint $table): void {
      if (Schema::hasColumn('memory_comments', 'edited_at')) {
        $table->dropColumn('edited_at');
      }
    });
  }
};
