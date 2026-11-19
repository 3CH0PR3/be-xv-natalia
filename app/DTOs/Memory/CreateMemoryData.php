<?php

namespace App\DTOs\Memory;

final readonly class CreateMemoryData
{
  /**
   * @param  array<int, \Illuminate\Http\UploadedFile>  $files
   * @param  array<int, \Illuminate\Http\UploadedFile|null>  $posters
   */
  public function __construct(
    public string $eventCode,
    public string $guestName,
    public string $tableCode,
    public ?string $ownerDeviceId,
    public ?string $ownerActorKey,
    public string $momentTag,
    public string $caption,
    public array $files,
    public array $posters = [],
    public ?string $externalId = null,
    public ?int $deviceLockId = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
      guestName: $data['guestName'] ?? '',
      tableCode: $data['tableCode'] ?? '',
      ownerDeviceId: $data['ownerDeviceId'] ?? null,
      ownerActorKey: $data['ownerActorKey'] ?? null,
      momentTag: $data['momentTag'] ?? 'Fiesta en Vivo',
      caption: $data['caption'] ?? '',
      files: $data['files'] ?? [],
      posters: $data['posters'] ?? [],
      externalId: $data['externalId'] ?? ($data['id'] ?? null),
      deviceLockId: isset($data['deviceLockId']) ? (int) $data['deviceLockId'] : null,
    );
  }

  public function toArray(): array
  {
    return [
      'externalId' => $this->externalId,
      'deviceLockId' => $this->deviceLockId,
      'eventCode' => $this->eventCode,
      'guestName' => $this->guestName,
      'tableCode' => $this->tableCode,
      'ownerDeviceId' => $this->ownerDeviceId,
      'ownerActorKey' => $this->ownerActorKey,
      'momentTag' => $this->momentTag,
      'caption' => $this->caption,
      'files' => $this->files,
      'posters' => $this->posters,
    ];
  }
}
