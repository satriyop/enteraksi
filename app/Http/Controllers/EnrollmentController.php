<?php

namespace App\Http\Controllers;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\CourseNotPublishedException;
use App\Domain\Shared\Exceptions\InvalidStateTransitionException;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Support\Helpers\DatabaseHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Note: EnrollmentService now returns Enrollment model.
// State transitions (drop, complete, reactivate) are owned by the Enrollment model.

class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentServiceContract $enrollmentService
    ) {}

    /**
     * Enroll the authenticated user in a course.
     *
     * Uses transaction with pessimistic locking to prevent race conditions
     * when multiple requests attempt to enroll simultaneously.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        $context = EnrollmentContext::for($user, $course);

        Gate::authorize('enroll', [$course, $context]);

        try {
            DB::transaction(function () use ($user, $course) {
                // Lock invitation row to prevent concurrent updates
                /** @var CourseInvitation|null $invitation */
                $invitation = CourseInvitation::query()
                    ->where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                $this->enrollmentService->enroll(
                    userId: $user->id,
                    courseId: $course->id,
                    invitedBy: $invitation?->invited_by,
                );

                // Update invitation inside same transaction
                if ($invitation) {
                    $invitation->update([
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]);
                }
            });

            return redirect()
                ->route('courses.show', $course)
                ->with('success', 'Berhasil mendaftar ke kursus.');

        } catch (AlreadyEnrolledException) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        } catch (CourseNotPublishedException) {
            return back()->with('error', 'Kursus ini belum dipublikasikan.');
        } catch (QueryException $e) {
            // Handle duplicate key violation (race condition fallback)
            if (DatabaseHelper::isDuplicateKeyException($e)) {
                return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
            }
            throw $e;
        }
    }

    /**
     * Re-enroll a user who previously dropped a course.
     */
    public function reenroll(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();

        // Find the dropped enrollment
        $droppedEnrollment = $this->enrollmentService->getDroppedEnrollment($user, $course);

        if (! $droppedEnrollment) {
            return back()->with('error', 'Tidak ada pendaftaran yang dibatalkan untuk kursus ini.');
        }

        $preserveProgress = $request->boolean('preserve_progress', true);

        // Use model method directly
        $droppedEnrollment->reactivate($preserveProgress);

        $message = $preserveProgress
            ? 'Berhasil melanjutkan kursus. Progress sebelumnya telah dipulihkan.'
            : 'Berhasil mendaftar ulang. Progress telah direset.';

        return redirect()
            ->route('courses.show', $course)
            ->with('success', $message);
    }

    /**
     * Remove the authenticated user from a course (drop/unenroll).
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();

        /** @var Enrollment $enrollment */
        $enrollment = Enrollment::query()
            ->forUserAndCourse($user, $course)
            ->firstOrFail();

        try {
            $enrollment->drop();

            return redirect()
                ->route('learner.dashboard')
                ->with('success', 'Pendaftaran kursus dibatalkan.');

        } catch (InvalidStateTransitionException) {
            return back()->with('error', 'Tidak dapat membatalkan pendaftaran ini.');
        }
    }

    /**
     * Accept a course invitation.
     *
     * Uses transaction with pessimistic locking to prevent race conditions
     * when multiple requests attempt to accept the same invitation.
     */
    public function acceptInvitation(Request $request, CourseInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        // Verify ownership first (doesn't need transaction)
        if ($invitation->user_id !== $user->id) {
            abort(403);
        }

        try {
            $courseId = DB::transaction(function () use ($user, $invitation) {
                // Lock and re-fetch invitation to prevent concurrent modifications
                $lockedInvitation = CourseInvitation::lockForUpdate()
                    ->findOrFail($invitation->id);

                // Re-check status inside transaction (may have changed)
                if ($lockedInvitation->status !== 'pending') {
                    throw new \RuntimeException('invitation_not_pending');
                }

                // Check if invitation has expired
                if ($lockedInvitation->is_expired) {
                    $lockedInvitation->update(['status' => 'expired']);
                    throw new \RuntimeException('invitation_expired');
                }

                $this->enrollmentService->enroll(
                    userId: $user->id,
                    courseId: $lockedInvitation->course_id,
                    invitedBy: $lockedInvitation->invited_by,
                );

                // Update invitation inside same transaction
                $lockedInvitation->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);

                return $lockedInvitation->course_id;
            });

            return redirect()
                ->route('courses.show', $courseId)
                ->with('success', 'Undangan diterima. Selamat belajar!');

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'invitation_not_pending') {
                return back()->with('error', 'Undangan ini sudah tidak berlaku.');
            }
            if ($e->getMessage() === 'invitation_expired') {
                return back()->with('error', 'Undangan ini sudah kadaluarsa.');
            }
            throw $e;
        } catch (AlreadyEnrolledException) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        } catch (CourseNotPublishedException) {
            return back()->with('error', 'Kursus ini belum dipublikasikan.');
        } catch (QueryException $e) {
            // Handle duplicate key violation (race condition fallback)
            if (DatabaseHelper::isDuplicateKeyException($e)) {
                return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
            }
            throw $e;
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
