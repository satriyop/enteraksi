<?php

namespace App\Domain\LearningPath\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\LearningPathEnrollment;

class PathEnrollmentCreated extends DomainEvent
{
    public function __construct(
        public readonly LearningPathEnrollment $enrollment,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'learning_path.enrollment.created';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'learning_path_id' => $this->enrollment->learning_path_id,
            'learning_path_title' => $this->enrollment->learningPath->title,
            'total_courses' => $this->enrollment->learningPath->courses()->count(),
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
