<?php

namespace App\Http\Controllers;

use App\Domain\Course\Contracts\CourseInvitationServiceContract;
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
    public function __construct(
        protected CourseInvitationServiceContract $invitationService
    ) {}

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

        $csv = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_map('strtolower', array_map('trim', $csv[0]));

        $emailIndex = array_search('email', $headers);
        if ($emailIndex === false) {
            return back()->withErrors(['file' => 'File CSV harus memiliki kolom "email".']);
        }

        $csvData = array_slice($csv, 1);

        $results = $this->invitationService->importFromCsv(
            csvData: $csvData,
            emailIndex: $emailIndex,
            courseId: $course->id,
            invitedBy: $request->user()->id,
            message: $request->validated('message'),
            expiresAt: $request->validated('expires_at'),
        );

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

        $excludeUserIds = [];
        if ($courseId) {
            $course = Course::find($courseId);
            if ($course) {
                $excludeUserIds = $course->getExcludedUserIdsForInvitation();
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
