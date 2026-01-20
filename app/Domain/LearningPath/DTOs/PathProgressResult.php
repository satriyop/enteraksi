<?php

namespace App\Domain\LearningPath\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Domain\Shared\ValueObjects\Percentage;

final class PathProgressResult extends DataTransferObject
{
    /**
     * @param  CourseProgressItem[]  $courses
     */
    public function __construct(
        public readonly int $pathEnrollmentId,
        public readonly Percentage $overallPercentage,
        public readonly int $totalCourses,
        public readonly int $completedCourses,
        public readonly int $inProgressCourses,
        public readonly int $lockedCourses,
        public readonly int $availableCourses,
        public readonly array $courses,
        public readonly bool $isCompleted,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            pathEnrollmentId: $data['path_enrollment_id'],
            overallPercentage: new Percentage($data['overall_percentage']),
            totalCourses: $data['total_courses'],
            completedCourses: $data['completed_courses'],
            inProgressCourses: $data['in_progress_courses'],
            lockedCourses: $data['locked_courses'],
            availableCourses: $data['available_courses'],
            courses: $data['courses'] ?? [],
            isCompleted: $data['is_completed'],
        );
    }

    public function toResponse(): array
    {
        return [
            'path_enrollment_id' => $this->pathEnrollmentId,
            'overall_percentage' => $this->overallPercentage->value,
            'total_courses' => $this->totalCourses,
            'completed_courses' => $this->completedCourses,
            'in_progress_courses' => $this->inProgressCourses,
            'locked_courses' => $this->lockedCourses,
            'available_courses' => $this->availableCourses,
            'courses' => array_map(fn ($c) => $c->toResponse(), $this->courses),
            'is_completed' => $this->isCompleted,
        ];
    }

    /**
     * Get the next course to work on (first in-progress or available).
     */
    public function getNextCourse(): ?CourseProgressItem
    {
        foreach ($this->courses as $course) {
            if ($course->status === 'in_progress') {
                return $course;
            }
        }

        foreach ($this->courses as $course) {
            if ($course->status === 'available') {
                return $course;
            }
        }

        return null;
    }
}
