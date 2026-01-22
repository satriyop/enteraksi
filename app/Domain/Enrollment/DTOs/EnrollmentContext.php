<?php

namespace App\Domain\Enrollment\DTOs;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Context for enrollment authorization decisions.
 *
 * This DTO allows controllers to pre-fetch enrollment data and pass it
 * to policies, avoiding queries inside authorization logic.
 *
 * @example
 * // In controller - fetch data once
 * $context = EnrollmentContext::for($user, $course);
 * Gate::authorize('enroll', [$course, $context]);
 *
 * // In policy - pure logic, no queries
 * public function enroll(User $user, Course $course, ?EnrollmentContext $context = null): bool
 * {
 *     $isEnrolled = $context?->isActivelyEnrolled ?? $this->checkEnrollment($user, $course);
 *     // ...
 * }
 */
readonly class EnrollmentContext
{
    public function __construct(
        public bool $isActivelyEnrolled,
        public bool $hasPendingInvitation,
        public bool $hasAnyEnrollment = false,
    ) {}

    /**
     * Create context by querying the database.
     *
     * Use this in controllers to pre-fetch the data before authorization.
     */
    public static function for(User $user, Course $course): self
    {
        // Single query to get enrollment status (more efficient)
        /** @var Enrollment|null $enrollment */
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->first(['id', 'status']);

        return new self(
            isActivelyEnrolled: $enrollment?->status === 'active',
            hasPendingInvitation: $user->courseInvitations()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->exists(),
            hasAnyEnrollment: $enrollment !== null,
        );
    }

    /**
     * Create context from pre-loaded data (for batch operations).
     */
    public static function fromData(
        bool $isActivelyEnrolled,
        bool $hasPendingInvitation,
        bool $hasAnyEnrollment = false,
    ): self {
        return new self(
            isActivelyEnrolled: $isActivelyEnrolled,
            hasPendingInvitation: $hasPendingInvitation,
            hasAnyEnrollment: $hasAnyEnrollment,
        );
    }
}
