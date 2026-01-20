<?php

namespace App\Domain\LearningPath\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\LearningPathEnrollment;

class PathProgressUpdated extends DomainEvent
{
    public function __construct(
        public readonly LearningPathEnrollment $enrollment,
        public readonly int $previousPercentage,
        public readonly int $newPercentage,
        public readonly ?int $completedCourseId = null,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'learning_path.progress.updated';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'learning_path_id' => $this->enrollment->learning_path_id,
            'previous_percentage' => $this->previousPercentage,
            'new_percentage' => $this->newPercentage,
            'progress_delta' => $this->newPercentage - $this->previousPercentage,
            'completed_course_id' => $this->completedCourseId,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'learning_path_enrollment';
    }
}
