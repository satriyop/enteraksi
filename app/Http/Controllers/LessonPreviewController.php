<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonPreviewController extends Controller
{
    /**
     * Show a lesson preview for non-enrolled users.
     */
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        // Verify the lesson belongs to this course
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        // Verify the course is published
        if ($course->status !== 'published') {
            abort(404);
        }

        // Verify the lesson is marked as free preview
        if (! $lesson->is_free_preview) {
            abort(403, 'Materi ini tidak tersedia untuk preview.');
        }

        $course->load([
            'category',
            'user',
            'sections.lessons',
        ]);

        $lesson->load(['section', 'media']);

        // Get user enrollment status if logged in
        $user = $request->user();
        $enrollment = $user?->enrollments()->where('course_id', $course->id)->first();

        return Inertia::render('courses/LessonPreview', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
        ]);
    }
}
