<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseReorderController extends Controller
{
    /**
     * Reorder sections within a course.
     */
    public function sections(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $request->validate([
            'sections' => ['required', 'array'],
            'sections.*' => ['required', 'integer', 'exists:course_sections,id'],
        ]);

        foreach ($request->sections as $order => $sectionId) {
            CourseSection::where('id', $sectionId)
                ->where('course_id', $course->id)
                ->update(['order' => $order + 1]);
        }

        return response()->json([
            'message' => 'Urutan bagian berhasil diperbarui.',
        ]);
    }

    /**
     * Reorder lessons within a section.
     */
    public function lessons(Request $request, CourseSection $section): JsonResponse
    {
        Gate::authorize('update', $section->course);

        $request->validate([
            'lessons' => ['required', 'array'],
            'lessons.*' => ['required', 'integer', 'exists:lessons,id'],
        ]);

        foreach ($request->lessons as $order => $lessonId) {
            $section->lessons()
                ->where('id', $lessonId)
                ->update(['order' => $order + 1]);
        }

        return response()->json([
            'message' => 'Urutan pelajaran berhasil diperbarui.',
        ]);
    }
}
