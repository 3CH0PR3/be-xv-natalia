<?php

namespace App\DTOs\Table;

use Illuminate\Http\Request;

final readonly class GenerateTableQrsData
{
  public function __construct(
    public string $eventCode,
    public ?string $baseUrl = null,
    public ?int $tableCount = null,
  ) {}

  public static function fromRequest(Request $request, ?string $eventCode = null): self
  {
    $code = $eventCode ?: $request->eventCode();
    $baseUrl = $request->input('baseUrl') ?: ($request->header('origin') ?: $request->getSchemeAndHttpHost());
    $tableCount = $request->filled('tableCount') ? (int) $request->input('tableCount') : null;

    return new self(
      eventCode: trim($code),
      baseUrl: $baseUrl,
      tableCount: $tableCount,
    );
  }
}
