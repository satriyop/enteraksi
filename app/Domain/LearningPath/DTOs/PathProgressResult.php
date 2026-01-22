<?php

namespace App\Domain\LearningPath\DTOs;

use App\Domain\Shared\ValueObjects\Percentage;

final readonly class PathProgressResult
{
    /**
     * @param  array[]  $courses
     */
    public function __construct(
        public int $pathEnrollmentId,
        public Percentage $overallPercentage,
        public int $totalCourses,
        public int $completedCourses,
        public int $inProgressCourses,
        public int $lockedCourses,
        public int $availableCourses,
        public array $courses,
        public bool $isCompleted,
        public int $requiredCourses = 0,
        public int $completedRequiredCourses = 0,
        public ?float $requiredPercentage = null,
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
            requiredCourses: $data['required_courses'] ?? 0,
            completedRequiredCourses: $data['completed_required_courses'] ?? 0,
            requiredPercentage: $data['required_percentage'] ?? null,
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
            'courses' => $this->courses,
            'is_completed' => $this->isCompleted,
            'required_courses' => $this->requiredCourses,
            'completed_required_courses' => $this->completedRequiredCourses,
            'required_percentage' => $this->requiredPercentage,
        ];
    }

    /**
     * Get the next course to work on (first in-progress or available).
     */
    public function getNextCourse(): ?array
    {
        foreach ($this->courses as $course) {
            if ($course['status'] === 'in_progress') {
                return $course;
            }
        }

        foreach ($this->courses as $course) {
            if ($course['status'] === 'available') {
                return $course;
            }
        }

        return null;
    }
}
