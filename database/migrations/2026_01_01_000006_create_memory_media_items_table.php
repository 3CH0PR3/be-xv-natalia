<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memory_media_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('external_id', 128);
            $table->string('media_blob_key', 128)->nullable();
            $table->enum('media_type', ['image', 'video'])->default('image');
            $table->string('disk', 40)->default('public');
            $table->string('file_path', 255);
            $table->string('thumb_path', 255)->nullable();
            $table->string('poster_path', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['memory_id', 'external_id'], 'uq_media_items_memory_ext');
            $table->index(['memory_id', 'sort_order'], 'idx_media_items_memory_order');
            $table->index('media_blob_key', 'idx_media_items_blob_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_media_items');
    }
};
