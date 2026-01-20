<?php

namespace App\Domain\Enrollment\Contracts;

use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

interface EnrollmentServiceContract
{
    /**
     * Enroll a user in a course.
     *
     * @throws \App\Domain\Enrollment\Exceptions\AlreadyEnrolledException
     * @throws \App\Domain\Enrollment\Exceptions\CourseNotPublishedException
     */
    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult;

    /**
     * Check if a user can enroll in a course.
     */
    public function canEnroll(User $user, Course $course): bool;

    /**
     * Get active enrollment for user and course.
     */
    public function getActiveEnrollment(User $user, Course $course): ?Enrollment;

    /**
     * Drop a user from a course.
     *
     * @throws \App\Domain\Shared\Exceptions\InvalidStateTransitionException
     */
    public function drop(Enrollment $enrollment, ?string $reason = null): void;

    /**
     * Mark enrollment as completed.
     * Usually called by ProgressTrackingService.
     */
    public function complete(Enrollment $enrollment): void;
}
