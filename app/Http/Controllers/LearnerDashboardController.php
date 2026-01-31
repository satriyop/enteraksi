<?php

namespace App\Http\Controllers;

use App\Http\Resources\Dashboard\DashboardCourseResource;
use App\Http\Resources\Dashboard\DashboardEnrollmentResource;
use App\Http\Resources\Dashboard\DashboardInvitationResource;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LearnerDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = Auth::user();

        // Only learners can access the learner dashboard
        if ($user->role !== 'learner') {
            abort(403);
        }

        // Featured courses for carousel (5 published public courses)
        $featuredCourses = Course::query()
            ->published()
            ->visible()
            ->with(['user:id,name', 'category:id,name'])
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get();

        // My learning - enrolled courses with progress (including completed courses)
        $myLearning = $user->enrollments()
            ->with(['course' => fn ($q) => $q->with(['user:id,name', 'category:id,name'])->withCount('lessons')])
            ->whereIn('status', ['active', 'completed'])
            ->orderByDesc('updated_at')
            ->get();

        // Invited courses - pending invitations
        $invitedCourses = $user->pendingInvitations()
            ->with([
                'course' => fn ($q) => $q->with(['user:id,name', 'category:id,name'])->withCount('lessons'),
                'inviter:id,name',
            ])
            ->get();

        // Browse courses - published public courses (excluding enrolled and invited)
        $enrolledCourseIds = $user->enrollments()->pluck('course_id')->toArray();
        $invitedCourseIds = $user->pendingInvitations()->pluck('course_id')->toArray();
        $excludeIds = array_merge($enrolledCourseIds, $invitedCourseIds);

        $browseCourses = Course::query()
            ->published()
            ->visible()
            ->when(count($excludeIds) > 0, fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->with(['user:id,name', 'category:id,name'])
            ->withCount('enrollments')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return Inertia::render('learner/Dashboard', [
            'featuredCourses' => $featuredCourses->map(
                fn ($course) => new DashboardCourseResource($course)
            ),
            'myLearning' => $myLearning->map(
                fn ($enrollment) => new DashboardEnrollmentResource($enrollment)
            ),
            'invitedCourses' => $invitedCourses->map(
                fn ($invitation) => new DashboardInvitationResource($invitation)
            ),
            'browseCourses' => $browseCourses->map(
                fn ($course) => new DashboardCourseResource($course)
            ),
        ]);
    }
}
