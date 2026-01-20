<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\DTOs\PathEnrollmentResult;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Domain\LearningPath\States\ActivePathState;
use App\Domain\LearningPath\States\AvailableCourseState;
use App\Domain\LearningPath\States\CompletedPathState;
use App\Domain\LearningPath\States\DroppedPathState;
use App\Domain\LearningPath\States\LockedCourseState;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Domain\Shared\Services\DomainLogger;
use App\Domain\Shared\Services\MetricsService;
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
        protected PrerequisiteEvaluatorFactory $evaluatorFactory
    ) {}

    public function enroll(User $user, LearningPath $path): PathEnrollmentResult
    {
        $this->validateEnrollment($user, $path);

        $this->logger->info('learning_path.enrollment.starting', [
            'user_id' => $user->id,
            'learning_path_id' => $path->id,
        ]);

        $startTime = microtime(true);

        try {
            $result = DB::transaction(function () use ($user, $path) {
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

                return new PathEnrollmentResult(
                    enrollment: $enrollment,
                    isNewEnrollment: true,
                );
            });

            $this->metrics->increment('learning_path.enrollments.created');
            $this->metrics->timing('learning_path.enrollment.duration', microtime(true) - $startTime);

            $this->logger->info('learning_path.enrollment.created', [
                'enrollment_id' => $result->enrollment->id,
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
            ]);

            return $result;
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

    public function canEnroll(User $user, LearningPath $path): bool
    {
        if ($this->getActiveEnrollment($user, $path)) {
            return false;
        }

        if (! $path->is_published) {
            return false;
        }

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
        if (! $enrollment->isActive()) {
            throw new InvalidStateTransitionException(
                from: (string) $enrollment->state,
                to: DroppedPathState::$name,
                modelType: 'LearningPathEnrollment',
                modelId: $enrollment->id,
                reason: 'Only active enrollments can be dropped'
            );
        }

        $this->logger->info('learning_path.drop.starting', [
            'enrollment_id' => $enrollment->id,
            'reason' => $reason,
        ]);

        DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->update([
                'state' => DroppedPathState::$name,
                'dropped_at' => now(),
                'drop_reason' => $reason,
            ]);

            PathDropped::dispatch($enrollment, $reason);
        });

        $this->metrics->increment('learning_path.enrollments.dropped');

        $this->logger->info('learning_path.drop.completed', [
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function complete(LearningPathEnrollment $enrollment): void
    {
        if ($enrollment->isCompleted()) {
            return; // Idempotent
        }

        $this->logger->info('learning_path.complete.starting', [
            'enrollment_id' => $enrollment->id,
        ]);

        $completedCourses = $enrollment->courseProgress()
            ->where('state', 'completed')
            ->count();

        DB::transaction(function () use ($enrollment, $completedCourses) {
            $enrollment->update([
                'state' => CompletedPathState::$name,
                'completed_at' => now(),
                'progress_percentage' => 100,
            ]);

            PathCompleted::dispatch($enrollment, $completedCourses);
        });

        $this->metrics->increment('learning_path.enrollments.completed');

        $this->logger->info('learning_path.complete.completed', [
            'enrollment_id' => $enrollment->id,
            'completed_courses' => $completedCourses,
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
        $courses = $path->courses()->orderBy('learning_path_course.position')->get();
        $evaluator = $this->evaluatorFactory->make($path);

        foreach ($courses as $index => $course) {
            // First course is always available, others are locked
            $isFirstCourse = $index === 0;
            $state = $isFirstCourse ? AvailableCourseState::$name : LockedCourseState::$name;

            $enrollment->courseProgress()->create([
                'course_id' => $course->id,
                'state' => $state,
                'position' => $course->pivot->position,
            ]);
        }
    }
}
