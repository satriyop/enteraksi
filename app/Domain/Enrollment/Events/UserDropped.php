<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;

class UserDropped extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly ?string $reason = null,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'enrollment.dropped';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'course_id' => $this->enrollment->course_id,
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
        return 'enrollment';
    }
}
