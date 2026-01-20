<?php

namespace App\Policies;

use App\Models\LearningPathEnrollment;
use App\Models\User;

class LearningPathEnrollmentPolicy
{
    /**
     * Determine whether the user can view any enrollments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the enrollment.
     */
    public function view(User $user, LearningPathEnrollment $enrollment): bool
    {
        // Users can view their own enrollments
        // Admins and instructors can view any enrollment
        return $user->id === $enrollment->user_id
            || $user->hasRole(['admin', 'instructor']);
    }

    /**
     * Determine whether the user can create enrollments.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can drop from the enrollment.
     */
    public function drop(User $user, LearningPathEnrollment $enrollment): bool
    {
        // Only the enrolled user can drop, and only if active
        return $user->id === $enrollment->user_id
            && $enrollment->isActive();
    }

    /**
     * Determine whether the user can update the enrollment.
     */
    public function update(User $user, LearningPathEnrollment $enrollment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the enrollment.
     */
    public function delete(User $user, LearningPathEnrollment $enrollment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the enrollment.
     */
    public function restore(User $user, LearningPathEnrollment $enrollment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the enrollment.
     */
    public function forceDelete(User $user, LearningPathEnrollment $enrollment): bool
    {
        return $user->hasRole('admin');
    }
}
