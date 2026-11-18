<?php

namespace App\DTOs\Memory;

final readonly class UpdateCaptionData
{
  public function __construct(
    public string $externalId,
    public string $caption,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      externalId: $data['externalId'] ?? '',
      caption: $data['caption'] ?? '',
    );
  }

  public function toArray(): array
  {
    return [
      'externalId' => $this->externalId,
      'caption' => $this->caption,
    ];
  }
}
