<?php

use App\Models\Memory;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});

Route::get('/share/{externalId}', function (string $externalId) {
  $memory = Memory::with(['mediaItems', 'deviceLock.table'])->where('external_id', $externalId)->first();
  $targetClientUrl = "https://xv-natalia-isabela.com/?foto=" . urlencode($externalId);

  if (!$memory) {
    return redirect($targetClientUrl);
  }

  $guestName = $memory->guest_name ?: 'Invitado';
  $title = "Recuerdo de {$guestName} · 15 Años de Natalia Isabella";
  $description = $memory->caption ? "\"{$memory->caption}\"" : "Recuerdo compartido en la fiesta de Natalia Isabella.";
  $firstMedia = $memory->mediaItems->first();
  $imageUrl = $memory->image_url ?: ($firstMedia?->image_url ?: 'https://xv-natalia-isabela.com/images/gala-hero-static.jpg');

  return response(
    <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>{$title}</title>
  <meta property="og:type" content="article">
  <meta property="og:title" content="{$title}">
  <meta property="og:description" content="{$description}">
  <meta property="og:image" content="{$imageUrl}">
  <meta property="og:image:secure_url" content="{$imageUrl}">
  <meta property="og:url" content="{$targetClientUrl}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{$title}">
  <meta name="twitter:description" content="{$description}">
  <meta name="twitter:image" content="{$imageUrl}">
  <meta http-equiv="refresh" content="0; url={$targetClientUrl}">
</head>
<body style="background-color: #11090b; color: #fff; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh;">
  <p>Abriendo recuerdo...</p>
  <script>window.location.replace("{$targetClientUrl}");</script>
</body>
</html>
HTML
  )->header('Content-Type', 'text/html; charset=UTF-8');
});

