<?php

namespace App\Http\Controllers;

use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function __construct(
        protected ProgressTrackingServiceContract $progressService
    ) {}

    /**
     * Update lesson progress for an enrolled user.
     */
    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:1'],
            'total_pages' => ['nullable', 'integer', 'min:1'],
            'pagination_metadata' => ['nullable', 'array'],
            'time_spent_seconds' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Verify the lesson belongs to this course
        $lessonCourse = $lesson->section->course;
        if ($lessonCourse->id !== $course->id) {
            abort(404);
        }

        // Get user enrollment
        $user = $request->user();
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
        $currentPage = $validated['current_page'];
        if (isset($validated['total_pages']) && $currentPage > $validated['total_pages']) {
            $currentPage = $validated['total_pages'];
        }

        // Use service to update progress
        $dto = new ProgressUpdateDTO(
            enrollmentId: $enrollment->id,
            lessonId: $lesson->id,
            currentPage: $currentPage,
            totalPages: $validated['total_pages'] ?? null,
            timeSpentSeconds: $validated['time_spent_seconds'] ?? null,
            metadata: $validated['pagination_metadata'] ?? null,
        );

        $result = $this->progressService->updateProgress($dto);

        return response()->json([
            'message' => 'Progress berhasil disimpan.',
            'progress' => [
                'current_page' => $result->progress->current_page,
                'total_pages' => $result->progress->total_pages,
                'highest_page_reached' => $result->progress->highest_page_reached,
                'is_completed' => $result->progress->is_completed,
                'progress_percentage' => $result->progress->progress_percentage,
                'time_spent_formatted' => $result->progress->time_spent_formatted,
                'pagination_metadata' => $result->progress->pagination_metadata,
            ],
            'enrollment' => [
                'progress_percentage' => $result->coursePercentage->value,
            ],
            'lesson_completed' => $result->lessonCompleted,
            'course_completed' => $result->courseCompleted,
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
        $user = $request->user();
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

        // Use service to update media progress
        $dto = new ProgressUpdateDTO(
            enrollmentId: $enrollment->id,
            lessonId: $lesson->id,
            mediaPositionSeconds: $position,
            mediaDurationSeconds: $validated['duration_seconds'],
        );

        $result = $this->progressService->updateProgress($dto);

        return response()->json([
            'message' => 'Progress media berhasil disimpan.',
            'progress' => [
                'media_position_seconds' => $result->progress->media_position_seconds,
                'media_duration_seconds' => $result->progress->media_duration_seconds,
                'media_progress_percentage' => $result->progress->media_progress_percentage,
                'is_completed' => $result->progress->is_completed,
            ],
            'enrollment' => [
                'progress_percentage' => $result->coursePercentage->value,
            ],
            'lesson_completed' => $result->lessonCompleted,
            'course_completed' => $result->courseCompleted,
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
        $user = $request->user();
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Anda tidak terdaftar di kursus ini.',
            ], 403);
        }

        // Use service to complete lesson
        $result = $this->progressService->completeLesson($enrollment, $lesson);

        return response()->json([
            'message' => 'Pelajaran selesai.',
            'progress' => [
                'is_completed' => $result->progress->is_completed,
                'completed_at' => $result->progress->completed_at,
            ],
            'enrollment' => [
                'progress_percentage' => $result->coursePercentage->value,
                'status' => $result->courseCompleted ? 'completed' : 'active',
            ],
            'lesson_completed' => $result->lessonCompleted,
            'course_completed' => $result->courseCompleted,
        ]);
    }
}
