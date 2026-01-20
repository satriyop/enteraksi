<?php

namespace App\Domain\LearningPath\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Models\LearningPathCourseProgress;
use DateTimeInterface;

final class CourseProgressItem extends DataTransferObject
{
    public function __construct(
        public readonly int $courseId,
        public readonly string $courseTitle,
        public readonly string $status,
        public readonly int $position,
        public readonly bool $isRequired,
        public readonly int $completionPercentage,
        public readonly ?int $minRequiredPercentage,
        public readonly ?array $prerequisites,
        public readonly ?string $lockReason = null,
        public readonly ?DateTimeInterface $unlockedAt = null,
        public readonly ?DateTimeInterface $startedAt = null,
        public readonly ?DateTimeInterface $completedAt = null,
        public readonly ?int $enrollmentId = null,
    ) {}

    public static function fromProgress(
        LearningPathCourseProgress $progress,
        array $pivotData = [],
        ?string $lockReason = null
    ): self {
        return new self(
            courseId: $progress->course_id,
            courseTitle: $progress->course->title ?? 'Unknown',
            status: (string) $progress->state,
            position: $progress->position,
            isRequired: $pivotData['is_required'] ?? true,
            completionPercentage: $progress->courseEnrollment?->progress_percentage ?? 0,
            minRequiredPercentage: $pivotData['min_completion_percentage'] ?? null,
            prerequisites: $pivotData['prerequisites'] ?? null,
            lockReason: $lockReason,
            unlockedAt: $progress->unlocked_at,
            startedAt: $progress->started_at,
            completedAt: $progress->completed_at,
            enrollmentId: $progress->learning_path_enrollment_id,
        );
    }

    public static function fromArray(array $data): static
    {
        return new self(
            courseId: $data['course_id'],
            courseTitle: $data['course_title'],
            status: $data['status'],
            position: $data['position'],
            isRequired: $data['is_required'],
            completionPercentage: $data['completion_percentage'],
            minRequiredPercentage: $data['min_required_percentage'] ?? null,
            prerequisites: $data['prerequisites'] ?? null,
            lockReason: $data['lock_reason'] ?? null,
        );
    }

    public function toResponse(): array
    {
        return [
            'course_id' => $this->courseId,
            'course_title' => $this->courseTitle,
            'status' => $this->status,
            'position' => $this->position,
            'is_required' => $this->isRequired,
            'completion_percentage' => $this->completionPercentage,
            'min_required_percentage' => $this->minRequiredPercentage,
            'lock_reason' => $this->lockReason,
            'unlocked_at' => $this->unlockedAt?->format('c'),
            'started_at' => $this->startedAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Get human-readable status label in Indonesian.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'locked' => 'Terkunci',
            'available' => 'Tersedia',
            'in_progress' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            default => $this->status,
        };
    }
}
