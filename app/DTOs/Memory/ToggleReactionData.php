<?php

namespace App\DTOs\Memory;

final readonly class ToggleReactionData
{
  public function __construct(
    public string $externalId,
    public string $actorKey,
    public ?string $deviceId,
    public string $choice,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      externalId: $data['externalId'] ?? '',
      actorKey: $data['actorKey'] ?? '',
      deviceId: $data['deviceId'] ?? null,
      choice: $data['choice'] ?? '',
    );
  }

  public function toArray(): array
  {
    return [
      'externalId' => $this->externalId,
      'actorKey' => $this->actorKey,
      'deviceId' => $this->deviceId,
      'choice' => $this->choice,
    ];
  }
}
