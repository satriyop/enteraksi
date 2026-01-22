<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Domain\LearningPath\States\ActivePathState;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedPathState;
use App\Domain\LearningPath\States\DroppedPathState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Domain\Shared\Services\DomainLogger;
use App\Domain\Shared\Services\MetricsService;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PathEnrollmentService implements PathEnrollmentServiceContract
{
    public function __construct(
        protected DomainLogger $logger,
        protected MetricsService $metrics,
        protected PrerequisiteEvaluatorFactory $evaluatorFactory,
        protected EnrollmentServiceContract $enrollmentService
    ) {}

    public function enroll(User $user, LearningPath $path, bool $preserveProgress = true): LearningPathEnrollment
    {
        $this->validateEnrollment($user, $path);

        // Check for existing dropped enrollment (re-enrollment case)
        $droppedEnrollment = $this->getDroppedEnrollment($user, $path);
        if ($droppedEnrollment) {
            return $this->reactivatePathEnrollment($droppedEnrollment, $preserveProgress);
        }

        $this->logger->info('learning_path.enrollment.starting', [
            'user_id' => $user->id,
            'learning_path_id' => $path->id,
        ]);

        $startTime = microtime(true);

        try {
            $enrollment = DB::transaction(function () use ($user, $path) {
                // Create the path enrollment
                $enrollment = LearningPathEnrollment::create([
                    'user_id' => $user->id,
                    'learning_path_id' => $path->id,
                    'state' => ActivePathState::$name,
                    'progress_percentage' => 0,
                    'enrolled_at' => now(),
                ]);

                // Initialize course progress for all courses in the path
                $this->initializeCourseProgress($enrollment, $path);

                PathEnrollmentCreated::dispatch($enrollment);

                return $enrollment;
            });

            $this->metrics->increment('learning_path.enrollments.created');
            $this->metrics->timing('learning_path.enrollment.duration', microtime(true) - $startTime);

            $this->logger->info('learning_path.enrollment.created', [
                'enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            return $enrollment;
        } catch (\Throwable $e) {
            $this->metrics->increment('learning_path.enrollments.failed');
            $this->logger->error('learning_path.enrollment.failed', [
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get dropped enrollment for a user in a learning path.
     */
    public function getDroppedEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment
    {
        return LearningPathEnrollment::query()
            ->where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->where('state', DroppedPathState::$name)
            ->first();
    }

    /**
     * Reactivate a dropped path enrollment (re-enrollment).
     *
     * Note: Named 'PathEnrollment' to distinguish from EnrollmentService's method.
     *
     * Best practice: Preserve progress by default to honor learner's previous work.
     * Set preserveProgress to false to reset the learner's progress.
     *
     * IMPORTANT: This default matches EnrollmentService::reactivateCourseEnrollment()
     * for consistent API behavior across all reactivation methods.
     */
    public function reactivatePathEnrollment(
        LearningPathEnrollment $enrollment,
        bool $preserveProgress = true
    ): LearningPathEnrollment {
        $this->logger->info('learning_path.enrollment.reactivating', [
            'enrollment_id' => $enrollment->id,
            'preserve_progress' => $preserveProgress,
        ]);

        DB::transaction(function () use ($enrollment, $preserveProgress) {
            $path = $enrollment->learningPath;

            // Reactivate the enrollment
            $enrollment->update([
                'state' => ActivePathState::$name,
                'enrolled_at' => now(),
                'dropped_at' => null,
                'drop_reason' => null,
                'completed_at' => null,
            ]);

            if ($preserveProgress) {
                // Keep existing course progress, just re-link course enrollments
                $this->relinkCourseEnrollments($enrollment);
            } else {
                // Delete old course progress and start fresh
                $enrollment->courseProgress()->delete();
                $enrollment->update(['progress_percentage' => 0]);
                $this->initializeCourseProgress($enrollment, $path);
            }

            PathEnrollmentCreated::dispatch($enrollment);
        });

        $this->metrics->increment('learning_path.enrollments.reactivated');

        $this->logger->info('learning_path.enrollment.reactivated', [
            'enrollment_id' => $enrollment->id,
            'preserve_progress' => $preserveProgress,
        ]);

        return $enrollment;
    }

    /**
     * Re-link course enrollments for reactivated path enrollment.
     * Creates new course enrollments for unlocked courses that don't have one.
     */
    protected function relinkCourseEnrollments(LearningPathEnrollment $enrollment): void
    {
        $user = $enrollment->user;

        foreach ($enrollment->courseProgress as $progress) {
            // Skip locked courses - they don't need enrollment yet
            if ($progress->isLocked()) {
                continue;
            }

            // If already has valid course enrollment, skip
            if ($progress->course_enrollment_id && $progress->courseEnrollment?->isActive()) {
                continue;
            }

            // Create/link course enrollment
            $courseEnrollment = $this->ensureCourseEnrollment($user, $progress->course);
            $progress->update(['course_enrollment_id' => $courseEnrollment->id]);
        }
    }

    public function canEnroll(User $user, LearningPath $path): bool
    {
        // Cannot enroll if already actively enrolled or completed
        if ($this->getActiveEnrollment($user, $path)) {
            return false;
        }

        // Must be published
        if (! $path->is_published) {
            return false;
        }

        // Allow re-enrollment for dropped enrollments
        return true;
    }

    public function getActiveEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment
    {
        return LearningPathEnrollment::query()
            ->where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->whereIn('state', [ActivePathState::$name, CompletedPathState::$name])
            ->first();
    }

    public function isEnrolled(User $user, LearningPath $path): bool
    {
        return $this->getActiveEnrollment($user, $path) !== null;
    }

    public function drop(LearningPathEnrollment $enrollment, ?string $reason = null): void
    {
        $this->logger->info('learning_path.drop.starting', [
            'enrollment_id' => $enrollment->id,
            'reason' => $reason,
        ]);

        // Model owns the state transition and event dispatch
        $enrollment->drop($reason);

        $this->metrics->increment('learning_path.enrollments.dropped');

        $this->logger->info('learning_path.drop.completed', [
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function complete(LearningPathEnrollment $enrollment): void
    {
        if ($enrollment->isCompleted()) {
            return; // Idempotent - check before logging
        }

        $this->logger->info('learning_path.complete.starting', [
            'enrollment_id' => $enrollment->id,
        ]);

        // Model owns the state transition and event dispatch
        $enrollment->complete();

        $this->metrics->increment('learning_path.enrollments.completed');

        $this->logger->info('learning_path.complete.completed', [
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function getActiveEnrollments(User $user): Collection
    {
        return LearningPathEnrollment::query()
            ->where('user_id', $user->id)
            ->where('state', ActivePathState::$name)
            ->with(['learningPath', 'courseProgress'])
            ->get();
    }

    protected function validateEnrollment(User $user, LearningPath $path): void
    {
        $existingEnrollment = $this->getActiveEnrollment($user, $path);
        if ($existingEnrollment) {
            throw new AlreadyEnrolledInPathException($user->id, $path->id);
        }

        if (! $path->is_published) {
            throw new PathNotPublishedException($path->id);
        }
    }

    protected function initializeCourseProgress(
        LearningPathEnrollment $enrollment,
        LearningPath $path
    ): void {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Course> $courses */
        $courses = $path->courses()->orderBy('learning_path_course.position')->get();
        $user = $enrollment->user;
        $noPrerequisites = $path->prerequisite_mode === 'none';

        foreach ($courses as $index => $course) {
            /** @var Course $course */
            // First course is always available
            // If prerequisite_mode is 'none', ALL courses are available
            $isFirstCourse = $index === 0;
            $isAvailable = $isFirstCourse || $noPrerequisites;
            $state = $isAvailable ? AvailableCourseState::$name : LockedCourseState::$name;

            // For available courses, create/link course enrollment
            $courseEnrollmentId = null;
            if ($isAvailable) {
                $courseEnrollment = $this->ensureCourseEnrollment($user, $course);
                $courseEnrollmentId = $courseEnrollment->id;
            }

            /** @var object{position: int} $pivot */
            $pivot = $course->pivot;
            $position = $pivot->position;

            $enrollment->courseProgress()->create([
                'course_id' => $course->id,
                'course_enrollment_id' => $courseEnrollmentId,
                'state' => $state,
                'position' => $position,
                'unlocked_at' => $isAvailable ? now() : null,
            ]);
        }
    }

    /**
     * Ensure user has an active course enrollment.
     * Reuses existing enrollment or creates new one.
     */
    public function ensureCourseEnrollment(User $user, Course $course): \App\Models\Enrollment
    {
        // Check if user already has an active enrollment for this course
        $existingEnrollment = $this->enrollmentService->getActiveEnrollment($user, $course);

        if ($existingEnrollment) {
            $this->logger->info('learning_path.course_enrollment.reused', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrollment_id' => $existingEnrollment->id,
            ]);

            return $existingEnrollment;
        }

        // Create new enrollment - now returns Enrollment model directly
        $enrollment = $this->enrollmentService->enroll(
            userId: $user->id,
            courseId: $course->id,
        );

        $this->logger->info('learning_path.course_enrollment.created', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
        ]);

        return $enrollment;
    }
}
