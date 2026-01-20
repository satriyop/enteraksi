<?php

namespace App\Domain\Progress\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\DTOs\ProgressResult;
use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Domain\Progress\Events\LessonCompleted;
use App\Domain\Progress\Events\ProgressUpdated;
use App\Domain\Shared\ValueObjects\Percentage;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\DB;

class ProgressTrackingService implements ProgressTrackingServiceContract
{
    public function __construct(
        protected ProgressCalculatorContract $calculator,
        protected EnrollmentServiceContract $enrollmentService,
    ) {}

    public function updateProgress(ProgressUpdateDTO $dto): ProgressResult
    {
        $enrollment = Enrollment::findOrFail($dto->enrollmentId);
        $lesson = Lesson::findOrFail($dto->lessonId);
        $progress = $this->getOrCreateProgress($enrollment, $lesson);

        return DB::transaction(function () use ($dto, $enrollment, $lesson, $progress) {
            $wasCompleted = $progress->is_completed;

            // Update based on progress type
            if ($dto->isMediaProgress()) {
                $this->updateMediaProgress($progress, $dto);
            } elseif ($dto->isPageProgress()) {
                $this->updatePageProgress($progress, $dto);
            }

            // Add time spent if provided
            if ($dto->timeSpentSeconds !== null) {
                $progress->time_spent_seconds += $dto->timeSpentSeconds;
            }

            $progress->last_viewed_at = now();
            $progress->save();

            // Update last lesson for resume feature
            $enrollment->update(['last_lesson_id' => $dto->lessonId]);

            // Check if lesson was just completed
            $justCompleted = ! $wasCompleted && $progress->is_completed;

            if ($justCompleted) {
                $this->handleLessonCompletion($enrollment, $lesson, $progress);
            }

            ProgressUpdated::dispatch($enrollment->fresh(), $progress->fresh());

            return new ProgressResult(
                progress: $progress->fresh(),
                coursePercentage: new Percentage($enrollment->fresh()->progress_percentage),
                lessonCompleted: $justCompleted,
                courseCompleted: $enrollment->fresh()->status === 'completed',
            );
        });
    }

    public function completeLesson(Enrollment $enrollment, Lesson $lesson): ProgressResult
    {
        $progress = $this->getOrCreateProgress($enrollment, $lesson);

        if ($progress->is_completed) {
            // Already complete - return current state
            return new ProgressResult(
                progress: $progress,
                coursePercentage: new Percentage($enrollment->progress_percentage),
                lessonCompleted: false,
                courseCompleted: $enrollment->status === 'completed',
            );
        }

        return DB::transaction(function () use ($enrollment, $lesson, $progress) {
            $progress->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);

            // Update last lesson for resume feature
            $enrollment->update(['last_lesson_id' => $lesson->id]);

            $this->handleLessonCompletion($enrollment, $lesson, $progress);

            return new ProgressResult(
                progress: $progress->fresh(),
                coursePercentage: new Percentage($enrollment->fresh()->progress_percentage),
                lessonCompleted: true,
                courseCompleted: $enrollment->fresh()->status === 'completed',
            );
        });
    }

    public function getOrCreateProgress(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        return LessonProgress::firstOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'current_page' => 1,
                'highest_page_reached' => 1,
                'time_spent_seconds' => 0,
                'is_completed' => false,
            ]
        );
    }

    public function recalculateCourseProgress(Enrollment $enrollment): float
    {
        $percentage = $this->calculator->calculate($enrollment);

        $enrollment->update([
            'progress_percentage' => $percentage,
        ]);

        // Check if enrollment should be marked complete
        if ($this->isEnrollmentComplete($enrollment) && $enrollment->status !== 'completed') {
            $this->enrollmentService->complete($enrollment);
        }

        return $percentage;
    }

    public function isEnrollmentComplete(Enrollment $enrollment): bool
    {
        return $this->calculator->isComplete($enrollment);
    }

    protected function updateMediaProgress(LessonProgress $progress, ProgressUpdateDTO $dto): void
    {
        $progress->media_position_seconds = $dto->mediaPositionSeconds;
        $progress->media_duration_seconds = $dto->mediaDurationSeconds;

        if ($dto->mediaDurationSeconds > 0) {
            $percentage = ($dto->mediaPositionSeconds / $dto->mediaDurationSeconds) * 100;
            $progress->media_progress_percentage = min(100, round($percentage, 2));

            // Auto-complete at 90% watched
            if ($percentage >= 90 && ! $progress->is_completed) {
                $progress->is_completed = true;
                $progress->completed_at = now();
            }
        }
    }

    protected function updatePageProgress(LessonProgress $progress, ProgressUpdateDTO $dto): void
    {
        $progress->current_page = $dto->currentPage;

        if ($dto->totalPages !== null) {
            $progress->total_pages = $dto->totalPages;
        }

        if ($dto->metadata !== null) {
            $progress->pagination_metadata = $dto->metadata;
        }

        // Update highest page reached
        if ($dto->currentPage > $progress->highest_page_reached) {
            $progress->highest_page_reached = $dto->currentPage;
        }

        // Auto-complete when reaching last page
        if ($progress->total_pages !== null &&
            $progress->highest_page_reached >= $progress->total_pages &&
            ! $progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }
    }

    protected function handleLessonCompletion(Enrollment $enrollment, Lesson $lesson, LessonProgress $progress): void
    {
        // Dispatch lesson completed event
        LessonCompleted::dispatch($enrollment, $lesson);

        // Recalculate course progress
        $this->recalculateCourseProgress($enrollment);
    }
}
