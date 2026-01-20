<?php

namespace App\Http\Controllers;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Models\Course;
use App\Models\CourseInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentServiceContract $enrollmentService
    ) {}

    /**
     * Enroll the authenticated user in a course.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('enroll', $course);

        $user = $request->user();

        // Check for pending invitation to get invited_by
        $invitation = $user->courseInvitations()
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->first();

        try {
            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $course->id,
                invitedBy: $invitation?->invited_by,
            );

            $this->enrollmentService->enroll($dto);

            // Update invitation status if exists
            if ($invitation) {
                $invitation->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
            }

            return redirect()
                ->route('courses.show', $course)
                ->with('success', 'Berhasil mendaftar ke kursus.');

        } catch (AlreadyEnrolledException) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        } catch (CourseNotPublishedException) {
            return back()->with('error', 'Kursus ini belum dipublikasikan.');
        }
    }

    /**
     * Remove the authenticated user from a course (drop/unenroll).
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();

        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->firstOrFail();

        try {
            $this->enrollmentService->drop($enrollment);

            return redirect()
                ->route('learner.dashboard')
                ->with('success', 'Pendaftaran kursus dibatalkan.');

        } catch (InvalidStateTransitionException) {
            return back()->with('error', 'Tidak dapat membatalkan pendaftaran ini.');
        }
    }

    /**
     * Accept a course invitation.
     */
    public function acceptInvitation(Request $request, CourseInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        // Verify the invitation belongs to the current user
        if ($invitation->user_id !== $user->id) {
            abort(403);
        }

        // Check if invitation is still pending
        if ($invitation->status !== 'pending') {
            return back()->with('error', 'Undangan ini sudah tidak berlaku.');
        }

        // Check if invitation has expired
        if ($invitation->is_expired) {
            $invitation->update(['status' => 'expired']);

            return back()->with('error', 'Undangan ini sudah kadaluarsa.');
        }

        try {
            $dto = new CreateEnrollmentDTO(
                userId: $user->id,
                courseId: $invitation->course_id,
                invitedBy: $invitation->invited_by,
            );

            $this->enrollmentService->enroll($dto);

            // Update invitation status
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            return redirect()
                ->route('courses.show', $invitation->course_id)
                ->with('success', 'Undangan diterima. Selamat belajar!');

        } catch (AlreadyEnrolledException) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        } catch (CourseNotPublishedException) {
            return back()->with('error', 'Kursus ini belum dipublikasikan.');
        }
    }

    /**
     * Decline a course invitation.
     */
    public function declineInvitation(Request $request, CourseInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        // Verify the invitation belongs to the current user
        if ($invitation->user_id !== $user->id) {
            abort(403);
        }

        // Check if invitation is still pending
        if ($invitation->status !== 'pending') {
            return back()->with('error', 'Undangan ini sudah tidak berlaku.');
        }

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return redirect()
            ->route('learner.dashboard')
            ->with('success', 'Undangan ditolak.');
    }
}
