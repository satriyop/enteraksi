<?php

namespace App\Domain\Progress\Listeners;

use App\Domain\Progress\Services\ProgressTrackingService;
use App\Domain\Progress\Events\LessonDeleted;
use App\Models\Enrollment;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateProgressOnLessonDeletion implements ShouldQueue
{
    public function __construct(
        protected ProgressTrackingService $progressService,
    ) {}

    public function handle(LessonDeleted $event): void
    {
        // Get all active enrollments for this course
        $activeEnrollments = Enrollment::query()
            ->where('course_id', $event->course->id)
            ->active()
            ->get();

        foreach ($activeEnrollments as $enrollment) {
            // Recalculate course progress (the calculator now excludes deleted lessons)
            $this->progressService->recalculateCourseProgress($enrollment);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['progress', 'lesson-deletion'];
    }
}
