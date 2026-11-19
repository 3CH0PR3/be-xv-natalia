<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (!Schema::hasColumn('event_tables', 'qr_token')) {
      Schema::table('event_tables', function (Blueprint $table): void {
        $table->string('qr_token', 64)->nullable()->index();
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('event_tables', 'qr_token')) {
      Schema::table('event_tables', function (Blueprint $table): void {
        $table->dropColumn('qr_token');
      });
    }
  }
};
