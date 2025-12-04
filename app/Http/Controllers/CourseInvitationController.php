<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkCourseInvitationRequest;
use App\Http\Requests\StoreCourseInvitationRequest;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(StoreCourseInvitationRequest $request, Course $course): RedirectResponse
    {
        $invitation = CourseInvitation::create([
            'user_id' => $request->validated('user_id'),
            'course_id' => $course->id,
            'invited_by' => $request->user()->id,
            'message' => $request->validated('message'),
            'expires_at' => $request->validated('expires_at'),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Undangan berhasil dikirim.');
    }

    /**
     * Bulk import invitations from CSV file.
     */
    public function bulkStore(BulkCourseInvitationRequest $request, Course $course): RedirectResponse
    {
        $file = $request->file('file');
        $message = $request->validated('message');
        $expiresAt = $request->validated('expires_at');

        $csv = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_map('strtolower', array_map('trim', $csv[0]));

        // Find email column index
        $emailIndex = array_search('email', $headers);
        if ($emailIndex === false) {
            return back()->withErrors(['file' => 'File CSV harus memiliki kolom "email".']);
        }

        $results = [
            'success' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get existing enrollments and pending invitations for this course
        $enrolledUserIds = $course->enrollments()
            ->where('status', 'active')
            ->pluck('user_id')
            ->toArray();

        $pendingInvitationUserIds = CourseInvitation::where('course_id', $course->id)
            ->where('status', 'pending')
            ->pluck('user_id')
            ->toArray();

        // Process each row (skip header)
        for ($i = 1; $i < count($csv); $i++) {
            $row = $csv[$i];
            if (empty($row) || ! isset($row[$emailIndex])) {
                continue;
            }

            $email = trim($row[$emailIndex]);
            if (empty($email)) {
                continue;
            }

            // Find learner user by email
            $user = User::where('email', $email)->where('role', 'learner')->first();

            if (! $user) {
                $results['errors'][] = "Baris {$i}: Email '{$email}' tidak ditemukan atau bukan learner.";
                $results['skipped']++;

                continue;
            }

            // Check if already enrolled
            if (in_array($user->id, $enrolledUserIds)) {
                $results['errors'][] = "Baris {$i}: '{$email}' sudah terdaftar di kursus.";
                $results['skipped']++;

                continue;
            }

            // Check if already has pending invitation
            if (in_array($user->id, $pendingInvitationUserIds)) {
                $results['errors'][] = "Baris {$i}: '{$email}' sudah memiliki undangan.";
                $results['skipped']++;

                continue;
            }

            // Create invitation
            CourseInvitation::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'invited_by' => $request->user()->id,
                'message' => $message,
                'expires_at' => $expiresAt,
                'status' => 'pending',
            ]);

            // Add to pending list to avoid duplicates in same batch
            $pendingInvitationUserIds[] = $user->id;
            $results['success']++;
        }

        $successMessage = "Berhasil mengirim {$results['success']} undangan.";
        if ($results['skipped'] > 0) {
            $successMessage .= " {$results['skipped']} dilewati.";
        }

        return back()
            ->with('success', $successMessage)
            ->with('import_errors', $results['errors']);
    }

    /**
     * Cancel/delete a pending invitation.
     */
    public function destroy(Course $course, CourseInvitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->delete();

        return back()->with('success', 'Undangan berhasil dibatalkan.');
    }

    /**
     * Search learners for invitation (AJAX endpoint).
     */
    public function searchLearners(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $courseId = $request->get('course_id');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Get IDs to exclude (already enrolled or have pending invitation)
        $excludeUserIds = [];

        if ($courseId) {
            $course = Course::find($courseId);
            if ($course) {
                $enrolledUserIds = $course->enrollments()
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->toArray();

                $pendingInvitationUserIds = CourseInvitation::where('course_id', $courseId)
                    ->where('status', 'pending')
                    ->pluck('user_id')
                    ->toArray();

                $excludeUserIds = array_merge($enrolledUserIds, $pendingInvitationUserIds);
            }
        }

        $learners = User::where('role', 'learner')
            ->whereNotIn('id', $excludeUserIds)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($learners);
    }
}
