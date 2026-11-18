<?php

namespace App\DTOs\Comment;

final readonly class ToggleCommentLikeData
{
    public function __construct(
        public string $memoryExternalId,
        public string $commentExternalId,
        public string $actorKey,
        public ?string $deviceId,
        public ?bool $liked,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            memoryExternalId: $data['memoryExternalId'] ?? '',
            commentExternalId: $data['commentExternalId'] ?? '',
            actorKey: $data['actorKey'] ?? '',
            deviceId: $data['deviceId'] ?? null,
            liked: isset($data['liked']) ? (bool) $data['liked'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'memoryExternalId' => $this->memoryExternalId,
            'commentExternalId' => $this->commentExternalId,
            'actorKey' => $this->actorKey,
            'deviceId' => $this->deviceId,
            'liked' => $this->liked,
        ];
    }
}
