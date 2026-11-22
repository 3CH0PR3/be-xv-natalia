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
      'qr_token' => ['nullable', 'string', 'max:128'],
      'qrToken' => ['nullable', 'string', 'max:128'],
      'rawPayload' => ['nullable', 'string', 'max:1000'],
    ];
  }

  public function toDto(): ValidateQrData
  {
    $eventCode = trim((string) ($this->input('eventCode') ?: config('xv.default_event_code', 'XV-NATALIA')));
    $tableCode = trim((string) ($this->input('tableCode') ?: $this->input('mesa', '')));
    $token = trim((string) ($this->input('token') ?: $this->input('qr_token') ?: $this->input('qrToken', '')));
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
    $raw = trim($raw);
    if ($raw === '') {
      return ['mesa' => '', 'token' => ''];
    }

    // 1. JSON payload support
    if (str_starts_with($raw, '{') && str_ends_with($raw, '}')) {
      $json = json_decode($raw, true);
      if (is_array($json)) {
        $mesa = $json['mesa'] ?? $json['table'] ?? $json['tableCode'] ?? $json['code'] ?? $json['id'] ?? '';
        $token = $json['token'] ?? $json['qr_token'] ?? $json['qrToken'] ?? $json['t'] ?? '';
        return [
          'mesa' => trim((string) $mesa),
          'token' => trim((string) $token),
        ];
      }
    }

    // 2. URL, hash/fragment or direct query string
    $queryStr = '';
    $parsedUrl = parse_url($raw);

    if (!empty($parsedUrl['query'])) {
      $queryStr = $parsedUrl['query'];
    } elseif (!empty($parsedUrl['fragment']) && str_contains($parsedUrl['fragment'], '?')) {
      $queryStr = substr($parsedUrl['fragment'], strpos($parsedUrl['fragment'], '?') + 1);
    } elseif (str_contains($raw, 'mesa=') || str_contains($raw, 'table=')) {
      $pos = strpos($raw, '?');
      $queryStr = $pos !== false ? substr($raw, $pos + 1) : $raw;
    }

    if ($queryStr !== '') {
      parse_str($queryStr, $params);
      $mesa = $params['mesa'] ?? $params['table'] ?? $params['tableCode'] ?? $params['slug'] ?? '';
      $token = $params['token'] ?? $params['qr_token'] ?? $params['qrToken'] ?? $params['t'] ?? '';
      return [
        'mesa' => trim((string) $mesa),
        'token' => trim((string) $token),
      ];
    }

    return ['mesa' => $raw, 'token' => ''];
  }
}
