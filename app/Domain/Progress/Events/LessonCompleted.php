<?php

namespace App\Domain\Progress\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;
use App\Models\Lesson;

class LessonCompleted extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
    }

    public function getEventName(): string
    {
        return 'lesson.completed';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'lesson_id' => $this->lesson->id,
            'lesson_title' => $this->lesson->title,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'course_progress' => $this->enrollment->progress_percentage,
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
