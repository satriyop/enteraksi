<?php

namespace App\Domain\Course\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Course;

class CoursePublished extends DomainEvent
{
    public function __construct(
        public readonly Course $course,
        public readonly ?string $previousStatus = null,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'course.published';
    }

    public function getMetadata(): array
    {
        return [
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'publisher_id' => $this->actorId,
            'previous_status' => $this->previousStatus,
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
