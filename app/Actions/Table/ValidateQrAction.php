<?php

namespace App\Actions\Table;

use App\DTOs\Table\ValidateQrData;
use App\Models\Event;
use App\Models\EventTable;

class ValidateQrAction
{
  /** @return array{isValid: bool, code: string, message: string, table: ?EventTable} */
  public function execute(ValidateQrData $data): array
  {
    $event = Event::byCode($data->eventCode)->first();
    if (!$event) {
      return [
        'isValid' => false,
        'code' => 'EVENT_NOT_FOUND',
        'message' => 'Este código QR pertenece a otro evento.',
        'table' => null,
      ];
    }

    if ($data->tableCode === '') {
      return [
        'isValid' => false,
        'code' => 'QR_MALFORMED',
        'message' => 'Código QR no reconocido o sin identificador de mesa.',
        'table' => null,
      ];
    }

    $table = EventTable::forEvent($event->id)
      ->matchingIdentifier($data->tableCode)
      ->first();

    if (!$table) {
      return [
        'isValid' => false,
        'code' => 'TABLE_NOT_FOUND',
        'message' => 'Código QR no válido o mesa no encontrada en este evento.',
        'table' => null,
      ];
    }

    $hasTokenMismatch = !empty($table->qr_token) &&
      (!$data->token || !hash_equals((string) $table->qr_token, (string) $data->token));

    if ($hasTokenMismatch) {
      return [
        'isValid' => false,
        'code' => 'QR_TOKEN_INVALID',
        'message' => 'Este código QR ha caducado o fue renovado por la anfitriona. Por favor escanea el código oficial actualizado de tu mesa.',
        'table' => null,
      ];
    }

    return [
      'isValid' => true,
      'code' => 'QR_VALID',
      'message' => "Código QR verificado correctamente para {$table->code}.",
      'table' => $table->load('deviceLocks'),
    ];
  }
}
