<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Domain\Enrollment\States\ActiveState;
use App\Domain\Enrollment\States\CompletedState;
use App\Domain\Enrollment\States\DroppedState;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment orchestration service.
 *
 * Handles enrollment creation/validation. State transitions (drop, complete, reactivate)
 * are now owned by the Enrollment model itself.
 */
class EnrollmentService
{
    /**
     * Enroll a user in a course.
     *
     * Returns the Enrollment model directly. Controllers transform using EnrollmentData.
     */
    public function enroll(
        int $userId,
        int $courseId,
        ?int $invitedBy = null,
        ?DateTimeInterface $enrolledAt = null
    ): Enrollment {
        $user = User::findOrFail($userId);
        $course = Course::findOrFail($courseId);

        $this->validateEnrollment($user, $course);

        // Check for existing dropped enrollment (re-enrollment case)
        $droppedEnrollment = $this->getDroppedEnrollment($user, $course);

        if ($droppedEnrollment) {
            // Reactivate via model method
            return $droppedEnrollment->reactivate(
                preserveProgress: true,
                invitedBy: $invitedBy
            );
        }

        return DB::transaction(function () use ($userId, $courseId, $invitedBy, $enrolledAt) {
            $enrollment = Enrollment::create([
                'user_id' => $userId,
                'course_id' => $courseId,
                'status' => ActiveState::$name,
                'progress_percentage' => 0,
                'enrolled_at' => $enrolledAt ?? now(),
                'invited_by' => $invitedBy,
            ]);

            UserEnrolled::dispatch($enrollment);

            return $enrollment;
        });
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

    public function getDroppedEnrollment(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', DroppedState::$name)
            ->first();
    }

    protected function validateEnrollment(User $user, Course $course): void
    {
        $existingEnrollment = $this->getActiveEnrollment($user, $course);
        if ($existingEnrollment) {
            throw new AlreadyEnrolledException($user->id, $course->id);
        }

        if (! $course->isPublished()) {
            throw new CourseNotPublishedException($course->id);
        }
    }

    /**
     * Build an enrollment status map for a list of courses.
     *
     * Returns array keyed by course_id with status and progress for a given user.
     * Used by course index/browse pages to show enrollment badges.
     *
     * @param  iterable<Course>  $courses
     * @return array<int, array{status: string, progress_percentage: int|null}>
     */
    public function getEnrollmentMapForCourses(User $user, iterable $courses): array
    {
        $courseIds = collect($courses)->pluck('id')->toArray();

        return Enrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->mapWithKeys(fn (Enrollment $e) => [
                $e->course_id => [
                    'status' => $e->status->getValue(),
                    'progress_percentage' => $e->progress_percentage,
                ],
            ])
            ->toArray();
    }

    /**
     * Check if user has a pending (usable) invitation for the course.
     */
    protected function hasValidInvitation(User $user, Course $course): bool
    {
        return $course->invitations()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }
}
