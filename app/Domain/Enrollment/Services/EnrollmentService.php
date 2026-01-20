<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Events\UserReenrolled;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Domain\Enrollment\States\ActiveState;
use App\Domain\Enrollment\States\CompletedState;
use App\Domain\Enrollment\States\DroppedState;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService implements EnrollmentServiceContract
{
    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult
    {
        $user = User::findOrFail($dto->userId);
        $course = Course::findOrFail($dto->courseId);

        $this->validateEnrollment($user, $course);

        // Check for existing dropped enrollment (re-enrollment case)
        $droppedEnrollment = $this->getDroppedEnrollment($user, $course);

        if ($droppedEnrollment) {
            return $this->reactivateEnrollment($droppedEnrollment, $dto);
        }

        return DB::transaction(function () use ($dto) {
            $enrollment = Enrollment::create([
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
                'status' => ActiveState::$name,
                'progress_percentage' => 0,
                'enrolled_at' => $dto->enrolledAt ?? now(),
                'invited_by' => $dto->invitedBy,
            ]);

            UserEnrolled::dispatch($enrollment);

            return new EnrollmentResult(
                enrollment: $enrollment,
                isNewEnrollment: true,
            );
        });
    }

    /**
     * Reactivate a dropped enrollment (re-enrollment).
     *
     * Best practice: Preserve progress by default to honor learner's previous work.
     * Set preserveProgress to false if you want to reset the learner's progress.
     */
    public function reactivateEnrollment(
        Enrollment $enrollment,
        CreateEnrollmentDTO $dto,
        bool $preserveProgress = true
    ): EnrollmentResult {
        return DB::transaction(function () use ($enrollment, $dto, $preserveProgress) {
            $updateData = [
                'status' => ActiveState::$name,
                'enrolled_at' => $dto->enrolledAt ?? now(),
                'completed_at' => null,
            ];

            // Optionally reset progress
            if (! $preserveProgress) {
                $updateData['progress_percentage'] = 0;
                $updateData['started_at'] = null;
                $updateData['last_lesson_id'] = null;
            }

            // Update invited_by if provided (might be re-invited by different trainer)
            if ($dto->invitedBy) {
                $updateData['invited_by'] = $dto->invitedBy;
            }

            $enrollment->update($updateData);

            UserReenrolled::dispatch($enrollment, $preserveProgress);

            return new EnrollmentResult(
                enrollment: $enrollment->fresh(),
                isNewEnrollment: false,
                message: $preserveProgress
                    ? 'Enrollment reactivated with previous progress preserved'
                    : 'Enrollment reactivated with progress reset',
            );
        });
    }

    /**
     * Get dropped enrollment for a user in a course.
     */
    public function getDroppedEnrollment(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', DroppedState::$name)
            ->first();
    }

    public function canEnroll(User $user, Course $course): bool
    {
        // Already actively enrolled?
        if ($this->getActiveEnrollment($user, $course)) {
            return false;
        }

        // Course published?
        if (! $course->isPublished()) {
            return false;
        }

        // Course visible to user?
        if ($course->visibility === 'hidden') {
            return false;
        }

        // Restricted course - check invitation or if user was previously enrolled (dropped)
        if ($course->visibility === 'restricted') {
            // Allow re-enrollment for previously dropped users
            if ($this->getDroppedEnrollment($user, $course)) {
                return true;
            }

            return $this->hasValidInvitation($user, $course);
        }

        return true;
    }

    public function getActiveEnrollment(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [ActiveState::$name, CompletedState::$name])
            ->first();
    }

    public function drop(Enrollment $enrollment, ?string $reason = null): void
    {
        // Can only drop active enrollments
        if (! $enrollment->isActive()) {
            throw new InvalidStateTransitionException(
                from: (string) $enrollment->status,
                to: DroppedState::$name,
                modelType: 'Enrollment',
                modelId: $enrollment->id,
                reason: 'Only active enrollments can be dropped'
            );
        }

        DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->update([
                'status' => DroppedState::$name,
            ]);

            UserDropped::dispatch($enrollment, $reason);
        });
    }

    public function complete(Enrollment $enrollment): void
    {
        if ($enrollment->isCompleted()) {
            return; // Idempotent - already complete
        }

        DB::transaction(function () use ($enrollment) {
            $enrollment->update([
                'status' => CompletedState::$name,
                'completed_at' => now(),
            ]);

            EnrollmentCompleted::dispatch($enrollment);
        });
    }

    protected function validateEnrollment(User $user, Course $course): void
    {
        // Check for active enrollment
        $existingEnrollment = $this->getActiveEnrollment($user, $course);
        if ($existingEnrollment) {
            throw new AlreadyEnrolledException($user->id, $course->id);
        }

        if (! $course->isPublished()) {
            throw new CourseNotPublishedException($course->id);
        }
    }

    protected function hasValidInvitation(User $user, Course $course): bool
    {
        return $course->invitations()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }
}
