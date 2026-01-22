<?php

namespace App\Http\Controllers;

use App\Http\Requests\Course\UpdateCourseStatusRequest;
use App\Http\Requests\Course\UpdateCourseVisibilityRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CoursePublishController extends Controller
{
    /**
     * Publish a course.
     */
    public function publish(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('publish', $course);

        $course->publish($request->user());

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Kursus berhasil dipublikasikan.');
    }

    /**
     * Unpublish a course (set back to draft).
     */
    public function unpublish(Course $course): RedirectResponse
    {
        Gate::authorize('unpublish', $course);

        $course->unpublish();

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Kursus berhasil di-unpublish.');
    }

    /**
     * Archive a course.
     */
    public function archive(Course $course): RedirectResponse
    {
        Gate::authorize('archive', $course);

        $course->archive();

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Kursus berhasil diarsipkan.');
    }

    /**
     * Update course status (LMS Admin only).
     */
    public function updateStatus(UpdateCourseStatusRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('setStatus', $course);

        $course->updateStatus($request->validated('status'), $request->user());

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Status kursus berhasil diperbarui.');
    }

    /**
     * Update course visibility (LMS Admin only).
     */
    public function updateVisibility(UpdateCourseVisibilityRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('setVisibility', $course);

        $validated = $request->validated();

        $course->update($validated);

        return redirect()
            ->route('courses.edit', $course)
            ->with('success', 'Visibilitas kursus berhasil diperbarui.');
    }
}
