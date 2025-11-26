<?php

namespace App\Policies;

use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LessonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageCourses();
    }

    /**
     * Determine whether the user can view the model.
     * Users must be enrolled (active status) or be course managers/owners.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        $course = $lesson->section->course;

        // Course managers can view all lessons
        if ($user->canManageCourses()) {
            return true;
        }

        // Owner can always view their own course lessons
        if ($course->user_id === $user->id) {
            return true;
        }

        // For learners: must be enrolled with active status
        return $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, CourseSection $section): bool
    {
        return Gate::allows('update', $section->course);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        return Gate::allows('update', $lesson->section->course);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        return Gate::allows('update', $lesson->section->course);
    }

    /**
     * Determine whether the user can reorder lessons.
     */
    public function reorder(User $user, CourseSection $section): bool
    {
        return Gate::allows('update', $section->course);
    }
}
