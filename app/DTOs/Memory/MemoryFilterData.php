<?php

namespace App\DTOs\Memory;

final readonly class MemoryFilterData
{
    public function __construct(
        public string $eventCode,
        public ?string $status,
        public bool $includeAllStatuses,
        public ?string $ownerDeviceId,
        public ?string $category,
        public ?string $search,
        public int $perPage,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
            status: $data['status'] ?? null,
            includeAllStatuses: (bool) ($data['includeAllStatuses'] ?? false),
            ownerDeviceId: $data['ownerDeviceId'] ?? null,
            category: $data['category'] ?? null,
            search: $data['search'] ?? null,
            perPage: (int) ($data['perPage'] ?? 12),
        );
    }

    public function toArray(): array
    {
        return [
            'eventCode' => $this->eventCode,
            'status' => $this->status,
            'includeAllStatuses' => $this->includeAllStatuses,
            'ownerDeviceId' => $this->ownerDeviceId,
            'category' => $this->category,
            'search' => $this->search,
            'perPage' => $this->perPage,
        ];
    }
}
