<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    /**
     * Update lesson progress for an enrolled user.
     */
    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'current_page'        => ['required', 'integer', 'min:1'],
            'total_pages'         => ['nullable', 'integer', 'min:1'],
            'pagination_metadata' => ['nullable', 'array'],
            'time_spent_seconds'  => ['nullable', 'numeric', 'min:0'],
        ]);

        // Verify the lesson belongs to this course
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        // Get user enrollment
        $user       = $request->user();
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Anda tidak terdaftar di kursus ini.',
            ], 403);
        }

        // Validate page number against total if provided
        if (isset($validated['total_pages']) && $validated['current_page'] > $validated['total_pages']) {
            $validated['current_page'] = $validated['total_pages'];
        }

        // Get or create progress
        $progress = $enrollment->getOrCreateProgressForLesson($lesson);

        // Update progress
        $progress->updateProgress(
            $validated['current_page'],
            $validated['total_pages'] ?? null,
            $validated['pagination_metadata'] ?? null
        );

        // Add time spent if provided
        if (isset($validated['time_spent_seconds']) && $validated['time_spent_seconds'] > 0) {
            $progress->addTimeSpent($validated['time_spent_seconds']);
        }

        // Update last lesson on enrollment
        $enrollment->last_lesson_id = $lesson->id;
        $enrollment->save();

        return response()->json([
            'message'    => 'Progress berhasil disimpan.',
            'progress'   => [
                'current_page'         => $progress->current_page,
                'total_pages'          => $progress->total_pages,
                'highest_page_reached' => $progress->highest_page_reached,
                'is_completed'         => $progress->is_completed,
                'progress_percentage'  => $progress->progress_percentage,
                'time_spent_formatted' => $progress->time_spent_formatted,
                'pagination_metadata'  => $progress->pagination_metadata,
            ],
            'enrollment' => [
                'progress_percentage' => $enrollment->progress_percentage,
            ],
        ]);
    }

    /**
     * Update media (video/youtube/audio) progress.
     */
    public function updateMedia(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
        ]);

        // Verify the lesson belongs to this course
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        // Get user enrollment
        $user       = $request->user();
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Anda tidak terdaftar di kursus ini.',
            ], 403);
        }

        // Validate position doesn't exceed duration
        $position = min($validated['position_seconds'], $validated['duration_seconds']);

        // Get or create progress
        $progress = $enrollment->getOrCreateProgressForLesson($lesson);

        // Update media progress
        $progress->updateMediaProgress($position, $validated['duration_seconds']);

        // Update last lesson on enrollment
        $enrollment->last_lesson_id = $lesson->id;
        $enrollment->save();

        return response()->json([
            'message'    => 'Progress media berhasil disimpan.',
            'progress'   => [
                'media_position_seconds'    => $progress->media_position_seconds,
                'media_duration_seconds'    => $progress->media_duration_seconds,
                'media_progress_percentage' => $progress->media_progress_percentage,
                'is_completed'              => $progress->is_completed,
            ],
            'enrollment' => [
                'progress_percentage' => $enrollment->fresh()->progress_percentage,
            ],
        ]);
    }

    /**
     * Mark a lesson as completed.
     */
    public function complete(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        // Verify the lesson belongs to this course
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        // Get user enrollment
        $user       = $request->user();
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Anda tidak terdaftar di kursus ini.',
            ], 403);
        }

        // Get or create progress
        $progress = $enrollment->getOrCreateProgressForLesson($lesson);

        // Mark as completed if not already
        if (! $progress->is_completed) {
            $progress->markCompleted();
        }

        // Update last lesson on enrollment
        $enrollment->last_lesson_id = $lesson->id;
        $enrollment->save();

        return response()->json([
            'message'    => 'Pelajaran selesai.',
            'progress'   => [
                'is_completed' => $progress->is_completed,
                'completed_at' => $progress->completed_at,
            ],
            'enrollment' => [
                'progress_percentage' => $enrollment->fresh()->progress_percentage,
                'status'              => $enrollment->fresh()->status,
                'last_lesson_id'      => $enrollment->last_lesson_id,
            ],
        ]);
    }
}