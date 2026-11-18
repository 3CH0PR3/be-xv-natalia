<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('honoree_name', 120)->default('Natalia Isabella');
            $table->string('subtitle', 160)->nullable();
            $table->string('venue', 255)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->unsignedInteger('party_celebrations_count')->default(0);
            $table->string('db_scope', 64)->default('laravel-v1');
            $table->unsignedBigInteger('epoch')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
