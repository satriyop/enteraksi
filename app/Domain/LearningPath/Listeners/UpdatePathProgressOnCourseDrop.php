<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Events\PathProgressUpdated;
use App\Domain\LearningPath\States\ActivePathState;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\Shared\Services\DomainLogger;
use App\Models\LearningPathCourseProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Updates learning path progress when a course enrollment is dropped.
 *
 * When a learner drops a course:
 * 1. If the course was completed in a learning path, revert it to "available"
 * 2. Recalculate path progress percentage
 * 3. If path was completed, revert to "active" state
 *
 * NOTE: We do NOT re-lock downstream courses to avoid disrupting learner progress.
 */
class UpdatePathProgressOnCourseDrop implements ShouldQueue
{
    public string $queue = 'progress';

    public function __construct(
        protected PathProgressServiceContract $progressService,
        protected DomainLogger $logger
    ) {}

    public function handle(UserDropped $event): void
    {
        $courseEnrollment = $event->enrollment;

        // Find all path progress records linked to this course enrollment
        $pathProgresses = LearningPathCourseProgress::query()
            ->where('course_enrollment_id', $courseEnrollment->id)
            ->with('enrollment')
            ->get();

        if ($pathProgresses->isEmpty()) {
            return;
        }

        $this->logger->info('learning_path.course_dropped.processing', [
            'course_enrollment_id' => $courseEnrollment->id,
            'affected_paths' => $pathProgresses->count(),
        ]);

        foreach ($pathProgresses as $pathProgress) {
            $this->processDroppedCourse($pathProgress, $courseEnrollment);
        }
    }

    protected function processDroppedCourse(
        LearningPathCourseProgress $pathProgress,
        \App\Models\Enrollment $courseEnrollment
    ): void {
        $pathEnrollment = $pathProgress->enrollment;

        // Skip if path enrollment is already dropped
        if ($pathEnrollment->isDropped()) {
            return;
        }

        DB::transaction(function () use ($pathProgress, $pathEnrollment, $courseEnrollment) {
            $wasCompleted = $pathProgress->isCompleted();
            $previousPercentage = $pathEnrollment->progress_percentage;

            // Revert course progress to "available" (they've already unlocked it)
            // Keep the course_enrollment_id for history/audit purposes
            if ($wasCompleted) {
                $pathProgress->update([
                    'state' => AvailableCourseState::$name,
                    'completed_at' => null,
                ]);

                $this->logger->info('learning_path.course_progress.reverted', [
                    'path_enrollment_id' => $pathEnrollment->id,
                    'course_id' => $pathProgress->course_id,
                    'from_state' => CompletedCourseState::$name,
                    'to_state' => AvailableCourseState::$name,
                ]);
            }

            // Recalculate path progress
            $newPercentage = $this->progressService->calculateProgressPercentage($pathEnrollment);

            // Build update data
            $updateData = ['progress_percentage' => $newPercentage];

            // If path was completed, revert to active
            // Use state CLASS (not $name string) so Spatie state casting works correctly
            if ($pathEnrollment->isCompleted()) {
                $updateData['state'] = ActivePathState::class;
                $updateData['completed_at'] = null;

                $this->logger->info('learning_path.enrollment.reverted_to_active', [
                    'path_enrollment_id' => $pathEnrollment->id,
                    'reason' => 'Course enrollment dropped',
                ]);
            }

            $pathEnrollment->update($updateData);

            // Dispatch progress update event if percentage changed
            if ($newPercentage !== $previousPercentage) {
                PathProgressUpdated::dispatch(
                    $pathEnrollment,
                    $previousPercentage,
                    $newPercentage,
                    $courseEnrollment->course_id
                );
            }
        });
    }
}
