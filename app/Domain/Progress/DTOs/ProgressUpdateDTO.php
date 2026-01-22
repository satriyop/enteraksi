<?php

namespace App\Domain\Progress\DTOs;

final readonly class ProgressUpdateDTO
{
    public function __construct(
        public int $enrollmentId,
        public int $lessonId,
        public ?int $currentPage = null,
        public ?int $totalPages = null,
        public ?float $timeSpentSeconds = null,
        public ?int $mediaPositionSeconds = null,
        public ?int $mediaDurationSeconds = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            enrollmentId: $data['enrollment_id'],
            lessonId: $data['lesson_id'],
            currentPage: $data['current_page'] ?? null,
            totalPages: $data['total_pages'] ?? null,
            timeSpentSeconds: $data['time_spent_seconds'] ?? null,
            mediaPositionSeconds: $data['media_position_seconds'] ?? null,
            mediaDurationSeconds: $data['media_duration_seconds'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function isPageProgress(): bool
    {
        return $this->currentPage !== null;
    }

    public function isMediaProgress(): bool
    {
        return $this->mediaPositionSeconds !== null && $this->mediaDurationSeconds !== null;
    }
}
