<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;

class EnrollmentCompleted extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'enrollment.completed';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'user_name' => $this->enrollment->user->name,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'progress_percentage' => $this->enrollment->progress_percentage,
            'enrolled_at' => $this->enrollment->enrolled_at?->toIso8601String(),
            'completed_at' => $this->enrollment->completed_at?->toIso8601String(),
            'total_time_spent' => $this->enrollment->lessonProgress()->sum('time_spent_seconds'),
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
