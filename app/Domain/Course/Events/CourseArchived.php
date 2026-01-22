<?php

namespace App\Domain\Course\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Course;
use App\Models\Enrollment;

class CourseArchived extends DomainEvent
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
        return 'course.archived';
    }

    public function getMetadata(): array
    {
        return [
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'previous_status' => $this->previousStatus,
            'total_enrollments' => $this->course->enrollments()->count(),
            'completed_enrollments' => Enrollment::query()
                ->where('course_id', $this->course->id)
                ->completed()
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
