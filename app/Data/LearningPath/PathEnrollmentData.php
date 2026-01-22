<?php

namespace App\Data\LearningPath;

use App\Models\LearningPathEnrollment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PathEnrollmentData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $learning_path_id,
        public string $state,
        public int $progress_percentage,
        public ?string $enrolled_at,
        public ?string $completed_at,
        public ?string $dropped_at = null,
        public ?string $drop_reason = null,
    ) {}

    public static function fromModel(LearningPathEnrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id,
            user_id: $enrollment->user_id,
            learning_path_id: $enrollment->learning_path_id,
            state: (string) $enrollment->state,
            progress_percentage: $enrollment->progress_percentage ?? 0,
            enrolled_at: $enrollment->enrolled_at?->toIso8601String(),
            completed_at: $enrollment->completed_at?->toIso8601String(),
            dropped_at: $enrollment->dropped_at?->toIso8601String(),
            drop_reason: $enrollment->drop_reason,
        );
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->state === 'completed';
    }

    public function isDropped(): bool
    {
        return $this->state === 'dropped';
    }
}
