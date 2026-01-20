<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\DTOs\CourseProgressItem;
use App\Domain\LearningPath\DTOs\PathProgressResult;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Domain\LearningPath\Events\CourseUnlockedInPath;
use App\Domain\LearningPath\Events\PathProgressUpdated;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Domain\LearningPath\States\CompletedPathState;
use App\Domain\LearningPath\States\InProgressCourseState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Domain\Shared\Services\DomainLogger;
use App\Domain\Shared\Services\MetricsService;
use App\Domain\Shared\ValueObjects\Percentage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use Illuminate\Support\Facades\DB;

class PathProgressService implements PathProgressServiceContract
{
    public function __construct(
        protected DomainLogger $logger,
        protected MetricsService $metrics,
        protected PrerequisiteEvaluatorFactory $evaluatorFactory
    ) {}

    public function getProgress(LearningPathEnrollment $enrollment): PathProgressResult
    {
        // Load course progress with related data
        $courseProgresses = $enrollment->courseProgress()
            ->with(['course', 'courseEnrollment'])
            ->orderBy('position')
            ->get();

        // Build pivot data lookup from learning path courses
        $pivotDataByCourseId = $enrollment->learningPath->courses
            ->keyBy('id')
            ->map(fn ($course) => [
                'is_required' => $course->pivot->is_required ?? true,
                'min_completion_percentage' => $course->pivot->min_completion_percentage ?? null,
                'prerequisites' => $course->pivot->prerequisites ?? null,
            ]);

        $items = $courseProgresses->map(function ($progress) use ($pivotDataByCourseId) {
            $pivotData = $pivotDataByCourseId->get($progress->course_id, []);

            return CourseProgressItem::fromProgress($progress, $pivotData);
        })->all();

        $totalCourses = $courseProgresses->count();
        $completedCourses = $courseProgresses->filter(fn ($p) => $p->isCompleted())->count();
        $inProgressCourses = $courseProgresses->filter(fn ($p) => $p->isInProgress())->count();
        $lockedCourses = $courseProgresses->filter(fn ($p) => $p->isLocked())->count();
        $availableCourses = $courseProgresses->filter(fn ($p) => $p->isAvailable())->count();

        return new PathProgressResult(
            pathEnrollmentId: $enrollment->id,
            overallPercentage: Percentage::fromFraction($completedCourses, $totalCourses),
            totalCourses: $totalCourses,
            completedCourses: $completedCourses,
            inProgressCourses: $inProgressCourses,
            lockedCourses: $lockedCourses,
            availableCourses: $availableCourses,
            courses: $items,
            isCompleted: $enrollment->state instanceof CompletedPathState,
        );
    }

    public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int
    {
        $totalCourses = $enrollment->courseProgress()->count();

        if ($totalCourses === 0) {
            return 0;
        }

        $completedCourses = $enrollment->courseProgress()
            ->where('state', CompletedCourseState::$name)
            ->count();

        return (int) round(($completedCourses / $totalCourses) * 100);
    }

    public function checkPrerequisites(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        $evaluator = $this->evaluatorFactory->make($enrollment->learningPath);

        return $evaluator->evaluate($enrollment, $course);
    }

    public function isCourseUnlocked(LearningPathEnrollment $enrollment, Course $course): bool
    {
        $progress = $enrollment->courseProgress()
            ->where('course_id', $course->id)
            ->first();

        if (! $progress) {
            return false;
        }

        return ! $progress->state instanceof LockedCourseState;
    }

    public function unlockNextCourses(LearningPathEnrollment $enrollment): array
    {
        $unlockedCourses = [];
        $evaluator = $this->evaluatorFactory->make($enrollment->learningPath);

        $lockedProgresses = $enrollment->courseProgress()
            ->where('state', LockedCourseState::$name)
            ->orderBy('position')
            ->with('course')
            ->get();

        foreach ($lockedProgresses as $progress) {
            $result = $evaluator->evaluate($enrollment, $progress->course);

            if ($result->isMet) {
                $this->unlockCourse($progress);
                $unlockedCourses[] = $progress->course;

                CourseUnlockedInPath::dispatch(
                    $enrollment,
                    $progress->course,
                    $progress->position
                );

                $this->logger->info('learning_path.course.unlocked', [
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $progress->course_id,
                    'position' => $progress->position,
                ]);
            }
        }

        return $unlockedCourses;
    }

    public function onCourseCompleted(
        LearningPathEnrollment $pathEnrollment,
        Enrollment $courseEnrollment
    ): void {
        $this->logger->info('learning_path.course.completed', [
            'path_enrollment_id' => $pathEnrollment->id,
            'course_enrollment_id' => $courseEnrollment->id,
            'course_id' => $courseEnrollment->course_id,
        ]);

        $startTime = microtime(true);
        $previousPercentage = $pathEnrollment->progress_percentage;

        DB::transaction(function () use ($pathEnrollment, $courseEnrollment, $previousPercentage) {
            // Update course progress state to completed
            $courseProgress = $pathEnrollment->courseProgress()
                ->where('course_id', $courseEnrollment->course_id)
                ->first();

            if ($courseProgress && ! $courseProgress->isCompleted()) {
                $courseProgress->update([
                    'state' => CompletedCourseState::$name,
                    'completed_at' => now(),
                ]);
            }

            // Unlock next courses based on prerequisites
            $this->unlockNextCourses($pathEnrollment);

            // Update overall path progress
            $newPercentage = $this->calculateProgressPercentage($pathEnrollment);
            $pathEnrollment->progress_percentage = $newPercentage;
            $pathEnrollment->save();

            if ($newPercentage !== $previousPercentage) {
                PathProgressUpdated::dispatch(
                    $pathEnrollment,
                    $previousPercentage,
                    $newPercentage,
                    $courseEnrollment->course_id
                );
            }

            // Check if path is now complete
            if ($this->isPathCompleted($pathEnrollment)) {
                app(PathEnrollmentService::class)->complete($pathEnrollment);
            }
        });

        $this->metrics->timing('learning_path.course_completion.processing', microtime(true) - $startTime);
    }

    public function isPathCompleted(LearningPathEnrollment $enrollment): bool
    {
        $totalRequired = $enrollment->courseProgress()
            ->whereHas('pathCourse', function ($query) {
                $query->where('is_required', true);
            })
            ->count();

        // If no required courses defined, all courses must be completed
        if ($totalRequired === 0) {
            $totalRequired = $enrollment->courseProgress()->count();
        }

        $completedRequired = $enrollment->courseProgress()
            ->where('state', CompletedCourseState::$name)
            ->when($totalRequired > 0, function ($query) {
                $query->whereHas('pathCourse', function ($q) {
                    $q->where('is_required', true);
                });
            })
            ->count();

        return $completedRequired >= $totalRequired;
    }

    public function startCourse(LearningPathEnrollment $enrollment, Course $course): void
    {
        $progress = $enrollment->courseProgress()
            ->where('course_id', $course->id)
            ->first();

        if (! $progress) {
            return;
        }

        if ($progress->isAvailable()) {
            $progress->update([
                'state' => InProgressCourseState::$name,
                'started_at' => now(),
            ]);

            $this->logger->info('learning_path.course.started', [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
            ]);
        }
    }

    protected function unlockCourse(LearningPathCourseProgress $progress): void
    {
        if ($progress->isLocked()) {
            $progress->update([
                'state' => AvailableCourseState::$name,
                'unlocked_at' => now(),
            ]);
        }
    }
}
