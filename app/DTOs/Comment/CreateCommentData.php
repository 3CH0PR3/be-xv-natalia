<?php

namespace App\DTOs\Comment;

final readonly class CreateCommentData
{
  public function __construct(
    public string $memoryExternalId,
    public ?string $commentExternalId,
    public string $author,
    public string $table,
    public string $text,
    public ?string $ownerDeviceId,
    public ?string $ownerActorKey,
    public ?int $deviceLockId = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      memoryExternalId: $data['memoryExternalId'] ?? '',
      commentExternalId: $data['commentExternalId'] ?? null,
      author: $data['author'] ?? '',
      table: $data['table'] ?? '',
      text: $data['text'] ?? '',
      ownerDeviceId: $data['ownerDeviceId'] ?? null,
      ownerActorKey: $data['ownerActorKey'] ?? null,
      deviceLockId: isset($data['deviceLockId']) ? (int) $data['deviceLockId'] : null,
    );
  }

  public function toArray(): array
  {
    return [
      'memoryExternalId' => $this->memoryExternalId,
      'commentExternalId' => $this->commentExternalId,
      'deviceLockId' => $this->deviceLockId,
      'author' => $this->author,
      'table' => $this->table,
      'text' => $this->text,
      'ownerDeviceId' => $this->ownerDeviceId,
      'ownerActorKey' => $this->ownerActorKey,
    ];
  }
}
