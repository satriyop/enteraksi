<?php

namespace App\Data\Enrollment;

use App\Models\Enrollment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EnrollmentData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $course_id,
        public string $status,
        public int $progress_percentage,
        public ?string $enrolled_at,
        public ?string $started_at,
        public ?string $completed_at,
        public ?int $invited_by = null,
        public ?int $last_lesson_id = null,
    ) {}

    public static function fromModel(Enrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id,
            user_id: $enrollment->user_id,
            course_id: $enrollment->course_id,
            status: (string) $enrollment->status,
            progress_percentage: $enrollment->progress_percentage ?? 0,
            enrolled_at: $enrollment->enrolled_at?->toIso8601String(),
            started_at: $enrollment->started_at?->toIso8601String(),
            completed_at: $enrollment->completed_at?->toIso8601String(),
            invited_by: $enrollment->invited_by,
            last_lesson_id: $enrollment->last_lesson_id,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDropped(): bool
    {
        return $this->status === 'dropped';
    }
}
