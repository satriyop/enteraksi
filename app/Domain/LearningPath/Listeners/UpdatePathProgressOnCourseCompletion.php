<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Models\LearningPathCourseProgress;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Updates learning path progress when a course enrollment is completed.
 * This bridges the course enrollment completion to the path progress tracking.
 */
class UpdatePathProgressOnCourseCompletion implements ShouldQueue
{
    public string $queue = 'progress';

    public function __construct(
        protected PathProgressServiceContract $progressService
    ) {}

    public function handle(EnrollmentCompleted $event): void
    {
        $courseEnrollment = $event->enrollment;

        // Find all path progress records linked to this course enrollment
        $pathProgresses = LearningPathCourseProgress::query()
            ->where('course_enrollment_id', $courseEnrollment->id)
            ->with('enrollment')
            ->get();

        foreach ($pathProgresses as $pathProgress) {
            $pathEnrollment = $pathProgress->enrollment;

            // Only process if the path enrollment is still active
            if ($pathEnrollment->isActive()) {
                $this->progressService->onCourseCompleted(
                    $pathEnrollment,
                    $courseEnrollment
                );
            }
        }
    }
}
