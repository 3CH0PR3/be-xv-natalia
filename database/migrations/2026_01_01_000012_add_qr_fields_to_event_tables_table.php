<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_tables', function (Blueprint $table): void {
            $table->string('qr_image_path', 255)->nullable()->after('capacity');
            $table->string('qr_svg_path', 255)->nullable()->after('qr_image_path');
            $table->string('access_url', 500)->nullable()->after('qr_svg_path');
            $table->timestamp('qr_generated_at')->nullable()->after('access_url');
        });
    }

    public function down(): void
    {
        Schema::table('event_tables', function (Blueprint $table): void {
            $table->dropColumn(['qr_image_path', 'qr_svg_path', 'access_url', 'qr_generated_at']);
        });
    }
};
