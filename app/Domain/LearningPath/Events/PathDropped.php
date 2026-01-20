<?php

namespace App\Domain\LearningPath\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\LearningPathEnrollment;

class PathDropped extends DomainEvent
{
    public function __construct(
        public readonly LearningPathEnrollment $enrollment,
        public readonly ?string $reason = null,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'learning_path.dropped';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'learning_path_id' => $this->enrollment->learning_path_id,
            'learning_path_title' => $this->enrollment->learningPath->title,
            'reason' => $this->reason,
            'progress_at_drop' => $this->enrollment->progress_percentage,
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
