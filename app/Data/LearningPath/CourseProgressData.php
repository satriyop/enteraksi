<?php

namespace App\Data\LearningPath;

use App\Models\LearningPathCourseProgress;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CourseProgressData extends Data
{
    public function __construct(
        public int $course_id,
        public string $course_title,
        public string $status,
        public int $position,
        public bool $is_required,
        public int $completion_percentage,
        public ?int $min_required_percentage = null,
        /** @var array<int>|null */
        public ?array $prerequisites = null,
        public ?string $lock_reason = null,
        public ?string $unlocked_at = null,
        public ?string $started_at = null,
        public ?string $completed_at = null,
        public ?int $enrollment_id = null,
    ) {}

    public static function fromProgress(
        LearningPathCourseProgress $progress,
        array $pivotData = [],
        ?string $lockReason = null
    ): self {
        return new self(
            course_id: $progress->course_id,
            course_title: $progress->course->title ?? 'Unknown',
            status: (string) $progress->state,
            position: $progress->position,
            is_required: $pivotData['is_required'] ?? true,
            completion_percentage: $progress->courseEnrollment?->progress_percentage ?? 0,
            min_required_percentage: $pivotData['min_completion_percentage'] ?? null,
            prerequisites: $pivotData['prerequisites'] ?? null,
            lock_reason: $lockReason,
            unlocked_at: $progress->unlocked_at?->toIso8601String(),
            started_at: $progress->started_at?->toIso8601String(),
            completed_at: $progress->completed_at?->toIso8601String(),
            enrollment_id: $progress->learning_path_enrollment_id,
        );
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
