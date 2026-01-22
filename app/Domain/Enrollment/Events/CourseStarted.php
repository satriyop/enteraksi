<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;

/**
 * Dispatched when a learner starts a course (first lesson access).
 *
 * This event is useful for:
 * - Analytics (time-to-start after enrollment)
 * - Welcome sequences
 * - Engagement tracking
 */
class CourseStarted extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'enrollment.course_started';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'enrolled_at' => $this->enrollment->enrolled_at?->toIso8601String(),
            'started_at' => $this->enrollment->started_at?->toIso8601String(),
            'time_to_start_hours' => $this->enrollment->enrolled_at && $this->enrollment->started_at
                ? round($this->enrollment->enrolled_at->diffInHours($this->enrollment->started_at), 2)
                : null,
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
