<?php

namespace App\Actions\Memory;

use App\Models\Memory;

class GetMemoryPreviewAction
{
  /**
   * @return array{external_id: string, title: string, description: string, image_url: string}
   */
  public function execute(string $externalId): array
  {
    $memory = Memory::with(['mediaItems', 'deviceLock.table'])
      ->where('external_id', $externalId)
      ->firstOrFail();

    $guestName = $memory->resolved_guest_name ?: ($memory->guest_name ?: 'Invitado');
    $title = "Recuerdo de {$guestName} · 15 Años de Natalia Isabella";
    $caption = trim((string) ($memory->caption ?? ''));
    $description = $caption !== '' ? "\"{$caption}\"" : 'Recuerdo compartido en los 15 Años de Natalia Isabella.';

    $firstMedia = $memory->mediaItems->first(function ($m) {
      return $m->media_type === 'image' || !empty($m->poster_path);
    }) ?: $memory->mediaItems->first();

    $mediaUrls = $firstMedia?->publicUrls() ?? [];
    $isVideo = $firstMedia?->media_type === 'video';

    $candidateImage = '';
    if ($isVideo) {
      $candidateImage = $mediaUrls['posterUrl'] ?? ($mediaUrls['thumbUrl'] ?? '');
    } else {
      $candidateImage = $mediaUrls['imageUrl'] ?? ($mediaUrls['thumbUrl'] ?? '');
    }

    $fallbackImage = 'https://xv-natalia-isabela.com/images/gala-hero-static.jpg';
    $imageUrl = !empty($candidateImage) ? $candidateImage : $fallbackImage;

    $isHttpOnDomain = str_starts_with($imageUrl, 'http://') && str_contains($imageUrl, 'xv-natalia-isabela.com');
    $imageUrl = $isHttpOnDomain ? 'https://' . substr($imageUrl, 7) : $imageUrl;

    return [
      'external_id' => $memory->external_id,
      'title' => $title,
      'description' => $description,
      'image_url' => $imageUrl,
    ];
  }
}
