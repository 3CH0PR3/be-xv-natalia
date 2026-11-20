<?php

namespace App\Actions\Table;

use App\DTOs\Table\GenerateTableQrsData;
use App\Events\TableOccupancyUpdated;
use App\Models\Event;
use App\Models\EventTable;
use App\Support\TableCatalog;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class GenerateTableQrsAction
{
  public function execute(
    GenerateTableQrsData|Event $target,
    ?string $baseUrl = null,
    ?int $tableCount = null
  ): Collection {
    $event = $target instanceof GenerateTableQrsData
      ? Event::byCode($target->eventCode)->firstOrFail()
      : $target;

    $resolvedBaseUrl = $target instanceof GenerateTableQrsData
      ? $target->baseUrl
      : $baseUrl;

    $resolvedTableCount = $target instanceof GenerateTableQrsData
      ? $target->tableCount
      : $tableCount;

    $cleanBaseUrl = $this->resolveBaseUrl($resolvedBaseUrl);
    $disk = Storage::disk(config('xv.media_disk', 'public'));
    $targetDir = "qrs/{$event->code}";
    $disk->makeDirectory($targetDir);

    $this->syncEventTableCount($event, $resolvedTableCount);

    $tables = EventTable::forEvent($event->id)->ordered()->get();
    $renderer = new ImageRenderer(new RendererStyle(600), new SvgImageBackEnd());
    $writer = new Writer($renderer);

    foreach ($tables as $table) {
      $qrToken = EventTable::generateSecureQrToken();
      $accessUrl = $this->buildTableAccessUrl($cleanBaseUrl, $table->slug, $qrToken);
      $svgPath = "{$targetDir}/qr-{$table->slug}.svg";
      $pngPath = "{$targetDir}/qr-{$table->slug}.png";

      $svgContent = $writer->writeString($accessUrl);
      $disk->put($svgPath, $svgContent);

      $pngContent = $this->renderPngBytes($accessUrl);
      $disk->put($pngPath, $pngContent);

      $table->update([
        'qr_token' => $qrToken,
        'qr_image_path' => $pngPath,
        'qr_svg_path' => $svgPath,
        'access_url' => $accessUrl,
        'qr_generated_at' => now(),
      ]);
    }

    Cache::store('redis')->forget("tables:event:{$event->id}");

    $refreshed = EventTable::with('deviceLocks')->forEvent($event->id)->ordered()->get();

    $tablePayload = $refreshed->map(static fn (EventTable $t): array => [
      'id' => $t->slug,
      'number' => $t->number,
      'code' => $t->code,
      'capacity' => $t->capacity,
      'guestsConnected' => $t->deviceLocks->count(),
      'qrImageUrl' => $t->qr_image_url,
      'qrSvgUrl' => $t->qr_svg_url,
      'qrToken' => $t->qr_token,
      'accessUrl' => $t->access_url,
    ])->all();

    broadcast(new TableOccupancyUpdated($event->code, $tablePayload))->toOthers();

    return $refreshed;
  }

  private function syncEventTableCount(Event $event, ?int $tableCount): void
  {
    if ($tableCount === null || $tableCount < 1) {
      return;
    }

    $slice = TableCatalog::getSlice($tableCount);
    foreach ($slice as $row) {
      EventTable::updateOrCreate(
        ['event_id' => $event->id, 'slug' => $row['slug']],
        array_merge($row, ['capacity' => config('xv.table_default_capacity', 10)])
      );
    }

    $keepSlugs = array_column($slice, 'slug');
    EventTable::forEvent($event->id)
      ->whereNotIn('slug', $keepSlugs)
      ->doesntHave('deviceLocks')
      ->delete();
  }

  private function buildTableAccessUrl(string $baseUrl, string $tableSlug, string $qrToken): string
  {
    return $baseUrl . '/acceso?' . http_build_query([
      'mesa' => $tableSlug,
      'token' => $qrToken,
    ]);
  }

  private function resolveBaseUrl(?string $candidate): string
  {
    $trimmed = trim((string) $candidate);
    if ($trimmed !== '') {
      return rtrim($trimmed, '/');
    }

    $fromRequest = $this->resolveBaseUrlFromRequest();
    if ($fromRequest !== '') {
      return $fromRequest;
    }

    return rtrim(config('app.url', 'http://localhost:3000'), '/');
  }

  private function resolveBaseUrlFromRequest(): string
  {
    if (!app()->bound('request')) {
      return '';
    }

    $req = app('request');
    $origin = (string) $req->header('origin');
    if ($origin !== '') {
      return rtrim($origin, '/');
    }

    return rtrim($req->getSchemeAndHttpHost(), '/');
  }

  private function renderPngBytes(string $text): string
  {
    $qr = Encoder::encode($text, ErrorCorrectionLevel::M());
    $matrix = $qr->getMatrix();
    $matrixWidth = $matrix->getWidth();
    $matrixHeight = $matrix->getHeight();
    $scale = 16;
    $margin = 2;
    $size = ($matrixWidth + $margin * 2) * $scale;

    $im = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($im, 255, 255, 255);
    $dark = imagecolorallocate($im, 20, 10, 15);
    imagefill($im, 0, 0, $white);

    for ($y = 0; $y < $matrixHeight; $y++) {
      for ($x = 0; $x < $matrixWidth; $x++) {
        if ($matrix->get($x, $y) !== 1) {
          continue;
        }
        imagefilledrectangle(
          $im,
          ($x + $margin) * $scale,
          ($y + $margin) * $scale,
          ($x + $margin + 1) * $scale - 1,
          ($y + $margin + 1) * $scale - 1,
          $dark
        );
      }
    }

    ob_start();
    imagepng($im);
    $pngData = ob_get_clean() ?: '';
    imagedestroy($im);

    return $pngData;
  }
}
