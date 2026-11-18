<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (!Schema::hasColumn('events', 'db_scope')) {
                $table->string('db_scope', 64)->default('laravel-v1')->after('party_celebrations_count');
            }
            if (!Schema::hasColumn('events', 'epoch')) {
                $table->unsignedBigInteger('epoch')->default(1)->after('db_scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'db_scope')) {
                $table->dropColumn('db_scope');
            }
            if (Schema::hasColumn('events', 'epoch')) {
                $table->dropColumn('epoch');
            }
        });
    }
};
