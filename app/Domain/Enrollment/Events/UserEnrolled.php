<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;

class UserEnrolled extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'enrollment.created';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'invited_by' => $this->enrollment->invited_by,
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
