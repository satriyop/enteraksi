<?php

namespace App\Domain\LearningPath\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Course;
use App\Models\LearningPathEnrollment;

class CourseUnlockedInPath extends DomainEvent
{
    public function __construct(
        public readonly LearningPathEnrollment $enrollment,
        public readonly Course $course,
        public readonly int $coursePosition,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'learning_path.course.unlocked';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'learning_path_id' => $this->enrollment->learning_path_id,
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'course_position' => $this->coursePosition,
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
