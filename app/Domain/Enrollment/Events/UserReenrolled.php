<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;

class UserReenrolled extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly bool $progressPreserved = false,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'enrollment.reactivated';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'progress_preserved' => $this->progressPreserved,
            'previous_progress' => $this->enrollment->progress_percentage,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'enrollment';
    }
}
