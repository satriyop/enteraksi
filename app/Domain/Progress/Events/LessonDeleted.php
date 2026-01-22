<?php

namespace App\Domain\Progress\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Course;
use App\Models\Enrollment;

class LessonDeleted extends DomainEvent
{
    public function __construct(
        public readonly int $lessonId,
        public readonly Course $course,
        public readonly string $lessonTitle,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'progress.lesson_deleted';
    }

    public function getMetadata(): array
    {
        return [
            'lesson_id' => $this->lessonId,
            'lesson_title' => $this->lessonTitle,
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
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
