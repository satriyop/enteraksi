<?php

namespace App\Http\Controllers;

use App\Http\Requests\Section\ReorderCourseSectionsRequest;
use App\Http\Requests\Section\ReorderSectionLessonsRequest;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CourseReorderController extends Controller
{
    /**
     * Reorder sections within a course.
     */
    public function sections(ReorderCourseSectionsRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $validated = $request->validated();

        CourseSection::bulkUpdateOrder($course, $validated['sections']);

        return response()->json([
            'message' => 'Urutan bagian berhasil diperbarui.',
        ]);
    }

    /**
     * Reorder lessons within a section.
     */
    public function lessons(ReorderSectionLessonsRequest $request, CourseSection $section): JsonResponse
    {
        Gate::authorize('update', $section->course);

        $validated = $request->validated();

        \App\Models\Lesson::bulkUpdateOrder($section, $validated['lessons']);

        return response()->json([
            'message' => 'Urutan pelajaran berhasil diperbarui.',
        ]);
    }
}
