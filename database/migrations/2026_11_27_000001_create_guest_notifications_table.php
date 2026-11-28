<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('guest_notifications', function (Blueprint $table): void {
      $table->uuid('id')->primary();
      $table->string('event_code', 48)->index();
      // Destinatario — el dueño del álbum/comentario que recibe la notificación
      $table->string('owner_device_id', 128)->index();
      $table->string('owner_actor_key', 128)->index();
      // Tipo: reaction | comment | comment_like
      $table->string('type', 32);
      // Quién generó la notificación
      $table->string('actor_name', 160)->nullable();
      $table->string('actor_key', 128)->nullable();
      // Referencia al contenido
      $table->string('memory_external_id', 128)->nullable()->index();
      $table->string('memory_caption', 255)->nullable();
      $table->string('media_thumb_url', 500)->nullable();
      // Payload extra (tipo de reacción, texto de comentario, etc.)
      $table->string('extra', 500)->nullable();
      $table->boolean('is_read')->default(false)->index();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('guest_notifications');
  }
};
