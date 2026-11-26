<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final readonly class FfmpegVideoProcessor
{
  public const TRANSCODE_EXTENSIONS = [
    'avi',
    'ts',
    'mts',
    'm2ts',
    'mkv',
    'wmv',
    'flv',
    '3gp',
    '3g2',
    'ogv',
    'ogg',
  ];

  private const TRANSCODE_MIMES = [
    'video/x-msvideo',
    'video/avi',
    'video/msvideo',
    'video/mp2t',
    'video/x-matroska',
    'video/x-ms-wmv',
    'video/x-flv',
    'video/3gpp',
    'video/3gpp2',
    'video/ogg',
    'application/ogg',
  ];

  public function isEnabled(): bool
  {
    return (bool) config('xv.ffmpeg_enabled', true);
  }

  public function isAvailable(): bool
  {
    if (!$this->isEnabled()) {
      return false;
    }

    $ffmpeg = $this->resolveFfmpegBinary();

    try {
      return Process::timeout(10)->run([$ffmpeg, '-version'])->successful();
    } catch (\Throwable) {
      return false;
    }
  }

  public function requiresTranscode(string $originalName, ?string $mimeType = null): bool
  {
    $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext !== '' && in_array($ext, self::TRANSCODE_EXTENSIONS, true)) {
      return true;
    }

    $cleanMime = strtolower(trim(explode(';', (string) $mimeType)[0]));

    return $cleanMime !== '' && in_array($cleanMime, self::TRANSCODE_MIMES, true);
  }

  public function probeMetadata(string $inputAbsPath): array
  {
    $empty = [
      'hasVideo' => false,
      'hasAudio' => false,
      'duration' => null,
      'width' => null,
      'height' => null,
      'videoCodec' => null,
      'audioCodec' => null,
    ];

    if (!$this->isEnabled() || !is_file($inputAbsPath)) {
      return $empty;
    }

    $ffprobe = $this->resolveFfprobeBinary();

    try {
      $result = Process::timeout(30)->run([
        $ffprobe,
        '-v',
        'quiet',
        '-print_format',
        'json',
        '-show_format',
        '-show_streams',
        $inputAbsPath,
      ]);
      if (!$result->successful()) {
        return $empty;
      }

      $decoded = json_decode($result->output(), true);
      if (!is_array($decoded)) {
        return $empty;
      }

      $streams = is_array($decoded['streams'] ?? null) ? $decoded['streams'] : [];
      $videoStream = null;
      $audioStream = null;

      foreach ($streams as $stream) {
        if (!is_array($stream)) {
          continue;
        }
        $codecType = $stream['codec_type'] ?? null;
        if ($codecType === 'video' && $videoStream === null) {
          $videoStream = $stream;
        } elseif ($codecType === 'audio' && $audioStream === null) {
          $audioStream = $stream;
        }
      }

      $durationRaw = $decoded['format']['duration'] ?? ($videoStream['duration'] ?? null);

      return [
        'hasVideo' => $videoStream !== null,
        'hasAudio' => $audioStream !== null,
        'duration' => is_numeric($durationRaw) ? (float) $durationRaw : null,
        'width' => isset($videoStream['width']) && is_numeric($videoStream['width']) ? (int) $videoStream['width'] : null,
        'height' => isset($videoStream['height']) && is_numeric($videoStream['height']) ? (int) $videoStream['height'] : null,
        'videoCodec' => isset($videoStream['codec_name']) ? (string) $videoStream['codec_name'] : null,
        'audioCodec' => isset($audioStream['codec_name']) ? (string) $audioStream['codec_name'] : null,
      ];
    } catch (\Throwable) {
      return $empty;
    }
  }

  public function transcodeToMp4(string $inputAbsPath, string $outputAbsPath): bool
  {
    if (!$this->isEnabled() || !is_file($inputAbsPath)) {
      return false;
    }

    $ffmpeg = $this->resolveFfmpegBinary();

    $outDir = dirname($outputAbsPath);
    if (!is_dir($outDir)) {
      @mkdir($outDir, 0775, true);
    }

    try {
      $result = Process::timeout(480)->run([
        $ffmpeg,
        '-y',
        '-i',
        $inputAbsPath,
        '-c:v',
        'libx264',
        '-preset',
        'veryfast',
        '-crf',
        '23',
        '-pix_fmt',
        'yuv420p',
        '-vf',
        'scale=trunc(iw/2)*2:trunc(ih/2)*2',
        '-c:a',
        'aac',
        '-b:a',
        '128k',
        '-movflags',
        '+faststart',
        $outputAbsPath,
      ]);

      clearstatcache(true, $outputAbsPath);
      if ($result->successful() && is_file($outputAbsPath) && filesize($outputAbsPath) > 0) {
        return true;
      }

      if (is_file($outputAbsPath)) {
        @unlink($outputAbsPath);
      }

      Log::warning('[FFmpeg] Video transcode to MP4 did not succeed', [
        'input' => basename($inputAbsPath),
        'exit_code' => $result->exitCode(),
        'error' => mb_substr($result->errorOutput(), -400),
      ]);

      return false;
    } catch (\Throwable $e) {
      if (is_file($outputAbsPath)) {
        @unlink($outputAbsPath);
      }
      Log::warning('[FFmpeg] Video transcode exception', [
        'input' => basename($inputAbsPath),
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  public function extractPosterJpg(string $inputAbsPath, string $outputPosterAbsPath): bool
  {
    if (!$this->isEnabled() || !is_file($inputAbsPath)) {
      return false;
    }

    $ffmpeg = $this->resolveFfmpegBinary();

    $outDir = dirname($outputPosterAbsPath);
    if (!is_dir($outDir)) {
      @mkdir($outDir, 0775, true);
    }

    foreach (['00:00:00.500', '00:00:00.000'] as $seekPos) {
      try {
        $result = Process::timeout(60)->run([
          $ffmpeg,
          '-y',
          '-ss',
          $seekPos,
          '-i',
          $inputAbsPath,
          '-frames:v',
          '1',
          '-q:v',
          '3',
          $outputPosterAbsPath,
        ]);

        clearstatcache(true, $outputPosterAbsPath);
        if ($result->successful() && is_file($outputPosterAbsPath) && filesize($outputPosterAbsPath) > 0) {
          return true;
        }
      } catch (\Throwable) {
      }
    }

    if (is_file($outputPosterAbsPath)) {
      @unlink($outputPosterAbsPath);
    }

    return false;
  }

  public function resolveFfmpegBinary(): string
  {
    return $this->resolveBinary((string) config('xv.ffmpeg_bin', ''), 'ffmpeg');
  }

  public function resolveFfprobeBinary(): string
  {
    return $this->resolveBinary((string) config('xv.ffprobe_bin', ''), 'ffprobe');
  }

  private function resolveBinary(string $configured, string $name): string
  {
    $clean = trim($configured, " \t\n\r\0\x0B\"'");
    if ($clean !== '') {
      $isBareCommand = !str_contains($clean, '/') && !str_contains($clean, '\\');
      if ($isBareCommand || is_file($clean)) {
        return $clean;
      }
    }

    if (DIRECTORY_SEPARATOR === '\\') {
      $localAppData = getenv('LOCALAPPDATA') ?: '';
      if ($localAppData !== '') {
        $wingetPattern = $localAppData . '\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg*\\ffmpeg-*\\bin\\' . $name . '.exe';
        $matches = glob($wingetPattern);
        if (is_array($matches) && !empty($matches) && is_file($matches[0])) {
          return $matches[0];
        }
      }
    }

    return $name;
  }
}
