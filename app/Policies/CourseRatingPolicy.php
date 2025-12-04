<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseRating;
use App\Models\User;

class CourseRatingPolicy
{
    /**
     * Determine whether the user can create a rating for a course.
     * User must be enrolled in the course.
     */
    public function create(User $user, Course $course): bool
    {
        // User must be enrolled in the course
        if (! $user->enrollments()->where('course_id', $course->id)->exists()) {
            return false;
        }

        // User cannot rate the same course twice
        return ! CourseRating::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
    }

    /**
     * Determine whether the user can update the rating.
     * User must own the rating.
     */
    public function update(User $user, CourseRating $courseRating): bool
    {
        return $courseRating->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the rating.
     * User must own the rating or be an admin.
     */
    public function delete(User $user, CourseRating $courseRating): bool
    {
        return $courseRating->user_id === $user->id || $user->isLmsAdmin();
    }
}
