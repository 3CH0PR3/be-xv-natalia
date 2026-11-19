<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Table\ValidateQrData;
use Illuminate\Foundation\Http\FormRequest;

class ValidateQrRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'eventCode' => ['nullable', 'string', 'max:48'],
      'tableCode' => ['nullable', 'string', 'max:80'],
      'mesa' => ['nullable', 'string', 'max:80'],
      'token' => ['nullable', 'string', 'max:128'],
      'rawPayload' => ['nullable', 'string', 'max:1000'],
    ];
  }

  public function toDto(): ValidateQrData
  {
    $eventCode = trim((string) ($this->input('eventCode') ?: config('xv.default_event_code', 'XV-NATALIA')));
    $tableCode = trim((string) ($this->input('tableCode') ?: $this->input('mesa', '')));
    $token = trim((string) $this->input('token', ''));
    $rawPayload = trim((string) $this->input('rawPayload', ''));

    $parsed = $this->parseFromRawPayload($rawPayload);
    $finalTableCode = $tableCode !== '' ? $tableCode : ($parsed['mesa'] ?? '');
    $finalToken = $token !== '' ? $token : ($parsed['token'] ?? null);

    return new ValidateQrData(
      eventCode: $eventCode,
      tableCode: $finalTableCode,
      token: $finalToken ?: null,
      rawPayload: $rawPayload ?: null,
    );
  }

  /** @return array{mesa: string, token: string} */
  private function parseFromRawPayload(string $raw): array
  {
    if ($raw === '') {
      return ['mesa' => '', 'token' => ''];
    }

    $parsedUrl = parse_url($raw);
    $query = $parsedUrl['query'] ?? '';
    if ($query === '') {
      return ['mesa' => '', 'token' => ''];
    }

    parse_str($query, $params);
    $mesa = isset($params['mesa']) ? trim((string) $params['mesa']) : '';
    $token = isset($params['token']) ? trim((string) $params['token']) : '';

    return ['mesa' => $mesa, 'token' => $token];
  }
}
