<?php

namespace App\Services;

use App\Actions\Table\GenerateTableQrsAction;
use App\Actions\Table\ListTablesAction;
use App\Actions\Table\ValidateQrAction;
use App\DTOs\Table\GenerateTableQrsData;
use App\DTOs\Table\ValidateQrData;
use Illuminate\Support\Collection;

class TableService
{
  public function __construct(
    private readonly ListTablesAction $listTables,
    private readonly GenerateTableQrsAction $generateQrs,
    private readonly ValidateQrAction $validateQr,
  ) {}

  public function listTables(string $eventCode): Collection
  {
    return $this->listTables->execute($eventCode);
  }

  public function generateQrs(GenerateTableQrsData $data): Collection
  {
    return $this->generateQrs->execute($data);
  }

  /**
   * @return array{isValid: bool, code: string, message: string, table: mixed}
   */
  public function validateQr(ValidateQrData $data): array
  {
    return $this->validateQr->execute($data);
  }
}
