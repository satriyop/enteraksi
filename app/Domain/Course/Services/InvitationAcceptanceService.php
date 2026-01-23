<?php

namespace App\Domain\Course\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Models\User;

class InvitationAcceptanceService
{
    public function __construct(
        protected EnrollmentServiceContract $enrollmentService
    ) {}

    /**
     * Accept invitation with pessimistic locking.
     *
     * Returns course_id on success.
     *
     * @throws \RuntimeException if invitation not pending
     * @throws \RuntimeException if invitation expired
     * @throws \App\Domain\Enrollment\Exceptions\AlreadyEnrolledException
     * @throws \App\Domain\Enrollment\Exceptions\CourseNotPublishedException
     */
    public function acceptWithLocking(User $user, CourseInvitation $invitation): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $invitation) {
            $lockedInvitation = CourseInvitation::lockForUpdate()
                ->findOrFail($invitation->id);

            if ($lockedInvitation->status !== 'pending') {
                throw new \RuntimeException('invitation_not_pending');
            }

            if ($lockedInvitation->is_expired) {
                $lockedInvitation->update(['status' => 'expired']);
                throw new \RuntimeException('invitation_expired');
            }

            $enrollment = $this->accept($user, $lockedInvitation);

            return $enrollment->course_id;
        });
    }

    /**
     * Accept invitation without locking.
     */
    public function accept(User $user, CourseInvitation $invitation): Enrollment
    {
        $enrollment = $this->enrollmentService->enroll(
            userId: $user->id,
            courseId: $invitation->course_id,
            invitedBy: $invitation->invited_by,
        );

        $invitation->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return $enrollment;
    }
}
