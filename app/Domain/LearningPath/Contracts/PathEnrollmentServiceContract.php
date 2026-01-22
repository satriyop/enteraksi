<?php

namespace App\Domain\LearningPath\Contracts;

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

interface PathEnrollmentServiceContract
{
    /**
     * Enroll a user in a learning path.
     *
     * Note: preserveProgress only applies when re-enrolling (dropped → active).
     * Default is true to honor learner's previous work.
     *
     * @throws \App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException
     * @throws \App\Domain\LearningPath\Exceptions\PathNotPublishedException
     */
    public function enroll(User $user, LearningPath $path, bool $preserveProgress = true): LearningPathEnrollment;

    /**
     * Reactivate a dropped path enrollment.
     *
     * Default preserveProgress=true to honor learner's previous work.
     * This matches EnrollmentService::reactivateCourseEnrollment() for API consistency.
     */
    public function reactivatePathEnrollment(
        LearningPathEnrollment $enrollment,
        bool $preserveProgress = true
    ): LearningPathEnrollment;

    /**
     * Get a dropped enrollment for user and learning path.
     */
    public function getDroppedEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment;

    /**
     * Check if user can enroll in a path.
     */
    public function canEnroll(User $user, LearningPath $path): bool;

    /**
     * Get user's active enrollment for a path.
     */
    public function getActiveEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment;

    /**
     * Check if user is enrolled in a path.
     */
    public function isEnrolled(User $user, LearningPath $path): bool;

    /**
     * Drop user from a learning path.
     *
     * @throws \App\Domain\Shared\Exceptions\InvalidStateTransitionException
     */
    public function drop(LearningPathEnrollment $enrollment, ?string $reason = null): void;

    /**
     * Mark path enrollment as completed.
     */
    public function complete(LearningPathEnrollment $enrollment): void;

    /**
     * Get all active path enrollments for a user.
     *
     * @return Collection<int, LearningPathEnrollment>
     */
    public function getActiveEnrollments(User $user): Collection;
}
