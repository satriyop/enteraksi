<?php

namespace App\Domain\Enrollment\Contracts;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use DateTimeInterface;

/**
 * Enrollment service contract.
 *
 * Note: State transitions (drop, complete, reactivate) are now owned by the
 * Enrollment model. Call them directly: $enrollment->drop(), $enrollment->complete().
 */
interface EnrollmentServiceContract
{
    /**
     * Enroll a user in a course.
     *
     * Returns the Enrollment model. Controllers transform using EnrollmentData.
     *
     * @throws \App\Domain\Enrollment\Exceptions\AlreadyEnrolledException
     * @throws \App\Domain\Enrollment\Exceptions\CourseNotPublishedException
     */
    public function enroll(
        int $userId,
        int $courseId,
        ?int $invitedBy = null,
        ?DateTimeInterface $enrolledAt = null
    ): Enrollment;

    /**
     * Check if a user can enroll in a course.
     */
    public function canEnroll(User $user, Course $course): bool;

    /**
     * Get active enrollment for user and course.
     */
    public function getActiveEnrollment(User $user, Course $course): ?Enrollment;

    /**
     * Get a dropped enrollment for user and course.
     */
    public function getDroppedEnrollment(User $user, Course $course): ?Enrollment;
}
