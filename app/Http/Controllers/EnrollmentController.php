<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Support\Helpers\DatabaseHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function __construct(
        protected \App\Domain\Enrollment\Contracts\EnrollmentServiceContract $enrollmentService,
        protected \App\Domain\Course\Services\InvitationAcceptanceService $invitationAcceptanceService
    ) {}

    /**
     * Enroll the authenticated user in a course.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        $context = \App\Domain\Enrollment\DTOs\EnrollmentContext::for($user, $course);

        Gate::authorize('enroll', [$course, $context]);

        try {
            DB::transaction(function () use ($user, $course) {
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

        } catch (\App\Domain\Enrollment\Exceptions\AlreadyEnrolledException) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        } catch (\App\Domain\Enrollment\Exceptions\CourseNotPublishedException) {
            return back()->with('error', 'Kursus ini belum dipublikasikan.');
        } catch (QueryException $e) {
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

        $droppedEnrollment = $this->enrollmentService->getDroppedEnrollment($user, $course);

        if (! $droppedEnrollment) {
            return back()->with('error', 'Tidak ada pendaftaran yang dibatalkan untuk kursus ini.');
        }

        $preserveProgress = $request->boolean('preserve_progress', true);

        $droppedEnrollment->reactivate($preserveProgress);

        $message = $preserveProgress
            ? 'Berhasil melanjutkan kursus. Progress sebelumnya telah dipulihkan.'
            : 'Berhasil mendaftar ulang. Progress telah direset.';

        return redirect()
            ->route('courses.show', $course)
            ->with('success', $message);
    }

    /**
     * Remove the authenticated user from a course.
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();

        $enrollment = Enrollment::query()
            ->forUserAndCourse($user, $course)
            ->firstOrFail();

        try {
            $enrollment->drop();

            return redirect()
                ->route('learner.dashboard')
                ->with('success', 'Pendaftaran kursus dibatalkan.');

        } catch (\App\Domain\Shared\Exceptions\InvalidStateTransitionException) {
            return back()->with('error', 'Tidak dapat membatalkan pendaftaran ini.');
        }
    }

    /**
     * Accept a course invitation.
     */
    public function acceptInvitation(Request $request, CourseInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        if ($invitation->user_id !== $user->id) {
            abort(403);
        }

        try {
            $courseId = $this->invitationAcceptanceService->acceptWithLocking($user, $invitation);

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
        } catch (\App\Domain\Enrollment\Exceptions\AlreadyEnrolledException) {
            return back()->with('error', 'Anda sudah terdaftar di kursus ini.');
        } catch (\App\Domain\Enrollment\Exceptions\CourseNotPublishedException) {
            return back()->with('error', 'Kursus ini belum dipublikasikan.');
        } catch (QueryException $e) {
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

        if ($invitation->user_id !== $user->id) {
            abort(403);
        }

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
