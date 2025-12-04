<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\User;

class CourseInvitationPolicy
{
    /**
     * Determine whether the user can view any invitations for a course.
     */
    public function viewAny(User $user, Course $course): bool
    {
        // Course owner, lms_admin, or trainer can view invitations
        return $user->id === $course->user_id
            || in_array($user->role, ['lms_admin', 'trainer']);
    }

    /**
     * Determine whether the user can view the invitation.
     */
    public function view(User $user, CourseInvitation $invitation): bool
    {
        // The invited user can view their own invitation
        if ($user->id === $invitation->user_id) {
            return true;
        }

        // Course owner, lms_admin, or trainer can view
        return $user->id === $invitation->course->user_id
            || in_array($user->role, ['lms_admin', 'trainer']);
    }

    /**
     * Determine whether the user can create invitations for a course.
     */
    public function create(User $user, Course $course): bool
    {
        // Course owner, lms_admin, or trainer can invite
        return $user->id === $course->user_id
            || in_array($user->role, ['lms_admin', 'trainer']);
    }

    /**
     * Determine whether the user can delete/cancel the invitation.
     */
    public function delete(User $user, CourseInvitation $invitation): bool
    {
        // Can only cancel pending invitations
        if ($invitation->status !== 'pending') {
            return false;
        }

        // Inviter or lms_admin can cancel
        return $user->id === $invitation->invited_by
            || $user->role === 'lms_admin';
    }
}
