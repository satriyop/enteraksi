<?php

namespace App\Policies;

use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\User;

class AssessmentPolicy
{
    /**
     * Determine whether the user can view any assessments for a course.
     *
     * Requires EnrollmentContext for learners to avoid hidden queries.
     */
    public function viewAny(User $user, Course $course, ?EnrollmentContext $context = null): bool
    {
        // LMS Admin can view all assessments
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can view assessments for their own courses
        if ($user->isContentManager() && $course->user_id === $user->id) {
            return true;
        }

        // Learners can view published assessments for courses they're enrolled in
        if ($user->isLearner() && $context !== null) {
            return $context->hasAnyEnrollment;
        }

        return false;
    }

    /**
     * Determine whether the user can view the assessment.
     *
     * Requires EnrollmentContext for learners to avoid hidden queries.
     */
    public function view(User $user, Assessment $assessment, Course $course, ?EnrollmentContext $context = null): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // LMS Admin can view all assessments
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can view their own assessments
        if ($user->isContentManager() && $assessment->user_id === $user->id) {
            return true;
        }

        // Learners can view published assessments they can attempt
        if ($user->isLearner() && $assessment->status === 'published' && $context !== null) {
            return $context->hasAnyEnrollment;
        }

        return false;
    }

    /**
     * Determine whether the user can create assessments for a course.
     */
    public function create(User $user, Course $course): bool
    {
        // LMS Admin can create assessments for any course
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can create assessments for their own courses
        if ($user->isContentManager() && $course->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the assessment.
     */
    public function update(User $user, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // LMS Admin can update any assessment
        // Content managers can update their own assessments
        if ($user->isLmsAdmin() or ($user->isContentManager() && $assessment->user_id === $user->id)) {
            return true;
        }

        // Cannot update published assessments
        if ($assessment->status === 'published') {
            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the assessment.
     */
    public function delete(User $user, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // Cannot delete published assessments
        if ($assessment->status === 'published') {
            return false;
        }

        // LMS Admin can delete any assessment
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Content managers can delete their own assessments
        if ($user->isContentManager() && $assessment->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can publish the assessment.
     */
    public function publish(User $user, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // Only LMS Admin can publish assessments
        return $user->isLmsAdmin();
    }

    /**
     * Determine whether the user can attempt the assessment.
     */
    public function attempt(User $user, Assessment $assessment, Course $course): bool
    {
        // Check if assessment belongs to the course
        if ($assessment->course_id !== $course->id) {
            return false;
        }

        // Use the assessment model's method to determine if user can attempt
        return $assessment->canBeAttemptedBy($user);
    }

    /**
     * Determine whether the user can view an assessment attempt.
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
