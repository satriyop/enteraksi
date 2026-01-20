<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\User;

class AssessmentAttemptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssessmentAttempt $attempt): bool
    {
        // LMS Admin can view any attempt
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can view attempts for their own assessments
        if ($user->isContentManager() && $attempt->assessment->user_id === $user->id) {
            return true;
        }

        // Users can view their own attempts
        return $attempt->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssessmentAttempt $attempt): bool
    {
        // Only LMS Admin can update attempts
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssessmentAttempt $attempt): bool
    {
        // Only LMS Admin can delete attempts
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssessmentAttempt $attempt): bool
    {
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssessmentAttempt $attempt): bool
    {
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can view an assessment attempt (with context).
     */
    public function viewAttempt(User $user, AssessmentAttempt $attempt, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // Check if attempt belongs to the assessment
        if ($attempt->assessment_id !== $assessment->id) {
            return false;
        }

        // LMS Admin can view any attempt
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can view attempts for their own assessments
        if ($user->isContentManager() && $assessment->user_id === $user->id) {
            return true;
        }

        // Learners can view their own attempts
        if ($user->isLearner() && $attempt->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can submit an assessment attempt.
     */
    public function submitAttempt(User $user, AssessmentAttempt $attempt, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // Check if attempt belongs to the assessment
        if ($attempt->assessment_id !== $assessment->id) {
            return false;
        }

        // Check if attempt belongs to the user
        if ($attempt->user_id !== $user->id) {
            return false;
        }

        // Can only submit in-progress attempts
        return $attempt->isInProgress();
    }

    /**
     * Determine whether the user can grade an assessment attempt.
     */
    public function grade(User $user, AssessmentAttempt $attempt, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // Check if attempt belongs to the assessment
        if ($attempt->assessment_id !== $assessment->id) {
            return false;
        }

        // LMS Admin can grade any attempt
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can grade attempts for their own assessments
        if ($user->isContentManager() && $assessment->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
