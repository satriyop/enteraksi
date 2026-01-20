<?php

namespace App\Domain\LearningPath\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Models\LearningPathEnrollment;

final class PathEnrollmentResult extends DataTransferObject
{
    public function __construct(
        public readonly LearningPathEnrollment $enrollment,
        public readonly bool $isNewEnrollment,
        public readonly int $totalCourses = 0,
        public readonly int $unlockedCourses = 1,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            enrollment: $data['enrollment'],
            isNewEnrollment: $data['is_new_enrollment'] ?? true,
            totalCourses: $data['total_courses'] ?? 0,
            unlockedCourses: $data['unlocked_courses'] ?? 1,
        );
    }

    public function toResponse(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'is_new_enrollment' => $this->isNewEnrollment,
            'total_courses' => $this->totalCourses,
            'unlocked_courses' => $this->unlockedCourses,
            'status' => $this->enrollment->status,
        ];
    }
}
