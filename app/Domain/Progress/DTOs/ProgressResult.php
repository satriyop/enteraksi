<?php

namespace App\Domain\Progress\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Domain\Shared\ValueObjects\Percentage;
use App\Models\LessonProgress;

final class ProgressResult extends DataTransferObject
{
    public function __construct(
        public LessonProgress $progress,
        public Percentage $coursePercentage,
        public bool $lessonCompleted,
        public bool $courseCompleted,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            progress: $data['progress'],
            coursePercentage: new Percentage($data['course_percentage']),
            lessonCompleted: $data['lesson_completed'],
            courseCompleted: $data['course_completed'],
        );
    }

    public function toResponse(): array
    {
        return [
            'progress' => $this->progress->toArray(),
            'course_percentage' => $this->coursePercentage->value,
            'lesson_completed' => $this->lessonCompleted,
            'course_completed' => $this->courseCompleted,
        ];
    }
}
