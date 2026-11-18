<?php

declare(strict_types=1);

/**
 * Re-indents PHP files from 4-space indentation to 2-space indentation.
 * Strictly verifies syntax with php -l before overwriting.
 */

$directories = [
  __DIR__ . '/../app',
  __DIR__ . '/../bootstrap',
  __DIR__ . '/../config',
  __DIR__ . '/../database',
  __DIR__ . '/../routes',
  __DIR__ . '/../tests',
  __DIR__ . '/../scripts',
];

function reindentFile(string $filePath): bool
{
  $raw = file_get_contents($filePath);
  if ($raw === false) {
    return false;
  }

  $isCrlf = str_contains($raw, "\r\n");
  $eol = $isCrlf ? "\r\n" : "\n";
  $lines = explode($eol, $raw);

  $transformed = [];
  foreach ($lines as $line) {
    if (preg_match('/^( +)(.*)$/', $line, $matches)) {
      $spaces = strlen($matches[1]);
      $rest = $matches[2];

      if (str_starts_with($rest, '*') && ($spaces - 1) % 4 === 0) {
        $level = (int) (($spaces - 1) / 4);
        $newSpaces = ($level * 2) + 1;
        $transformed[] = str_repeat(' ', $newSpaces) . $rest;
      } elseif ($spaces % 4 === 0) {
        $level = (int) ($spaces / 4);
        $newSpaces = $level * 2;
        $transformed[] = str_repeat(' ', $newSpaces) . $rest;
      } else {
        $newSpaces = (int) round($spaces / 2);
        $transformed[] = str_repeat(' ', $newSpaces) . $rest;
      }
    } else {
      $transformed[] = $line;
    }
  }

  $newContent = implode($eol, $transformed);
  if ($newContent === $raw) {
    return false;
  }

  $tempFile = $filePath . '.tmp';
  file_put_contents($tempFile, $newContent);

  // Lint check
  $lintOutput = [];
  $lintStatus = 0;
  exec('php -l ' . escapeshellarg($tempFile) . ' 2>&1', $lintOutput, $lintStatus);

  if ($lintStatus !== 0) {
    unlink($tempFile);
    throw new RuntimeException("Syntax lint failed on {$filePath}:\n" . implode("\n", $lintOutput));
  }

  rename($tempFile, $filePath);
  return true;
}

$processed = 0;
$modified = 0;

foreach ($directories as $dir) {
  if (!is_dir($dir)) {
    continue;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
      $processed++;
      if (reindentFile($file->getPathname())) {
        $modified++;
      }
    }
  }
}

echo "Total PHP files processed: {$processed}\n";
echo "Files re-indented to 2 spaces: {$modified}\n";
