<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\AppRelease\SyncAppReleasesData;
use Illuminate\Foundation\Http\FormRequest;

class SyncAppReleasesRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'buildId' => ['nullable', 'string', 'max:128'],
      'builtAtMs' => ['nullable', 'integer'],
      'releases' => ['required', 'array', 'max:250'],
      'releases.*.version' => ['required', 'string', 'max:40'],
      'releases.*.type' => ['nullable', 'in:major,minor,patch'],
      'releases.*.date' => ['nullable', 'string', 'max:60'],
      'releases.*.title' => ['required', 'string', 'max:280'],
      'releases.*.highlights' => ['nullable', 'array', 'max:20'],
      'releases.*.commitSha' => ['nullable', 'string', 'max:64'],
    ];
  }

  public function toDto(): SyncAppReleasesData
  {
    return new SyncAppReleasesData(
      buildId: $this->filled('buildId') ? trim((string) $this->input('buildId')) : null,
      builtAtMs: (int) $this->input('builtAtMs', round(microtime(true) * 1000)),
      releases: $this->input('releases', []),
    );
  }
}
