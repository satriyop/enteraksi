<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    /**
     * Enroll the authenticated user in a course.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('enroll', $course);

        $user = $request->user();

        // Create enrollment
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // If enrolled via invitation, update the invitation status
        $invitation = $user->courseInvitations()
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->first();

        if ($invitation) {
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            // Set invited_by on enrollment
            $enrollment->update(['invited_by' => $invitation->invited_by]);
        }

        return redirect()
            ->route('courses.show', $course)
            ->with('success', 'Berhasil mendaftar ke kursus.');
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

        // Can only drop active enrollments
        if ($enrollment->status !== 'active') {
            return back()->with('error', 'Tidak dapat membatalkan pendaftaran ini.');
        }

        $enrollment->update(['status' => 'dropped']);

        return redirect()
            ->route('learner.dashboard')
            ->with('success', 'Pendaftaran kursus dibatalkan.');
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

        // Create enrollment
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $invitation->course_id,
            'status' => 'active',
            'enrolled_at' => now(),
            'invited_by' => $invitation->invited_by,
        ]);

        // Update invitation status
        $invitation->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return redirect()
            ->route('courses.show', $invitation->course_id)
            ->with('success', 'Undangan diterima. Selamat belajar!');
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
