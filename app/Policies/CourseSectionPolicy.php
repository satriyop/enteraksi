<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CourseSectionPolicy
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
     */
    public function view(User $user, CourseSection $section): bool
    {
        return Gate::allows('view', $section->course);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Course $course): bool
    {
        return Gate::allows('update', $course);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CourseSection $section): bool
    {
        return Gate::allows('update', $section->course);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CourseSection $section): bool
    {
        return Gate::allows('update', $section->course);
    }

    /**
     * Determine whether the user can reorder sections.
     */
    public function reorder(User $user, Course $course): bool
    {
        return Gate::allows('update', $course);
    }
}
