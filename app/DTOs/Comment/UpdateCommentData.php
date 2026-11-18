<?php

namespace App\DTOs\Comment;

final readonly class UpdateCommentData
{
    public function __construct(
        public string $memoryExternalId,
        public string $commentExternalId,
        public string $text,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            memoryExternalId: $data['memoryExternalId'] ?? '',
            commentExternalId: $data['commentExternalId'] ?? '',
            text: $data['text'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'memoryExternalId' => $this->memoryExternalId,
            'commentExternalId' => $this->commentExternalId,
            'text' => $this->text,
        ];
    }
}
