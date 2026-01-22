<?php

namespace App\Domain\Course\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Course;
use App\Models\Enrollment;

class CourseUnpublished extends DomainEvent
{
    public function __construct(
        public readonly Course $course,
        public readonly string $previousStatus,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'course.unpublished';
    }

    public function getMetadata(): array
    {
        return [
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'previous_status' => $this->previousStatus,
            'active_enrollments_count' => Enrollment::query()
                ->where('course_id', $this->course->id)
                ->active()
                ->count(),
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->course->id;
    }

    public function getAggregateType(): string
    {
        return 'course';
    }
}
