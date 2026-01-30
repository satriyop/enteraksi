<?php

namespace App\Http\Controllers;

use App\Domain\Progress\Services\ProgressTrackingService;
use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Http\Requests\LessonProgress\UpdateMediaProgressRequest;
use App\Http\Requests\LessonProgress\UpdatePaginationProgressRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function __construct(
        protected ProgressTrackingService $progressService
    ) {}

    /**
     * Update lesson progress for an enrolled user.
     */
    public function update(UpdatePaginationProgressRequest $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validated();

        $enrollment = $this->getActiveEnrollment($request, $course);
        if (! $enrollment) {
            return $this->enrollmentNotFoundResponse();
        }

        // Validate lesson belongs to course
        if ($lesson->section->course->id !== $course->id) {
            abort(404);
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
            'progress' => $result->progress,
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
    public function updateMedia(UpdateMediaProgressRequest $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validated();

        $enrollment = $this->getActiveEnrollment($request, $course);
        if (! $enrollment) {
            return $this->enrollmentNotFoundResponse();
        }

        // Validate lesson belongs to course
        if ($lesson->section->course->id !== $course->id) {
            abort(404);
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
            'progress' => $result->progress,
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
        $enrollment = $this->getActiveEnrollment($request, $course);
        if (! $enrollment) {
            return $this->enrollmentNotFoundResponse();
        }

        // Validate lesson belongs to course
        if ($lesson->section->course->id !== $course->id) {
            abort(404);
        }

        // Use service to complete lesson
        $result = $this->progressService->completeLesson($enrollment, $lesson);

        return response()->json([
            'message' => 'Pelajaran selesai.',
            'progress' => $result->progress,
            'enrollment' => [
                'progress_percentage' => $result->coursePercentage->value,
                'status' => $result->courseCompleted ? 'completed' : 'active',
            ],
            'lesson_completed' => $result->lessonCompleted,
            'course_completed' => $result->courseCompleted,
        ]);
    }

    /**
     * Get active enrollment for user and course.
     */
    protected function getActiveEnrollment(Request $request, Course $course): ?Enrollment
    {
        return Enrollment::getActiveForUserAndCourse($request->user(), $course);
    }

    /**
     * Return JSON response for when enrollment is not found.
     */
    protected function enrollmentNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Anda tidak terdaftar di kursus ini.',
        ], 403);
    }
}
