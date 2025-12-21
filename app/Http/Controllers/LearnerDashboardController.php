<?php
namespace App\Http\Controllers;

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
            ->get()
            ->map(fn($course) => [
                'id'                => $course->id,
                'title'             => $course->title,
                'slug'              => $course->slug,
                'short_description' => $course->short_description,
                'thumbnail_path'    => $course->thumbnail_url,
                'difficulty_level'  => $course->difficulty_level,
                'duration'          => $course->duration,
                'instructor'        => $course->user->name,
                'category'          => $course->category?->name,
                'enrollments_count' => $course->enrollments_count,
            ]);

        // My learning - enrolled courses with progress (including completed courses)
        $myLearning = $user->enrollments()
            ->with(['course' => fn($q) => $q->with(['user:id,name', 'category:id,name'])->withCount('lessons')])
            ->whereIn('status', ['active', 'completed'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($enrollment) => [
                'id'                  => $enrollment->id,
                'course_id'           => $enrollment->course->id,
                'title'               => $enrollment->course->title,
                'slug'                => $enrollment->course->slug,
                'short_description'   => $enrollment->course->short_description,
                'thumbnail_path'      => $enrollment->course->thumbnail_url,
                'difficulty_level'    => $enrollment->course->difficulty_level,
                'duration'            => $enrollment->course->duration,
                'instructor'          => $enrollment->course->user->name,
                'category'            => $enrollment->course->category?->name,
                'progress_percentage' => $enrollment->progress_percentage,
                'enrolled_at'         => $enrollment->enrolled_at->toDateTimeString(),
                'last_lesson_id'      => $enrollment->last_lesson_id,
                'lessons_count'       => $enrollment->course->lessons_count,
                'status'              => $enrollment->status,
            ]);

        // Invited courses - pending invitations
        $invitedCourses = $user->pendingInvitations()
            ->with([
                'course' => fn($q) => $q->with(['user:id,name', 'category:id,name'])->withCount('lessons'),
                'inviter:id,name',
            ])
            ->get()
            ->map(fn($invitation) => [
                'id'                => $invitation->id,
                'course_id'         => $invitation->course->id,
                'title'             => $invitation->course->title,
                'slug'              => $invitation->course->slug,
                'short_description' => $invitation->course->short_description,
                'thumbnail_path'    => $invitation->course->thumbnail_url,
                'difficulty_level'  => $invitation->course->difficulty_level,
                'duration'          => $invitation->course->duration,
                'instructor'        => $invitation->course->user->name,
                'category'          => $invitation->course->category?->name,
                'lessons_count'     => $invitation->course->lessons_count,
                'invited_by'        => $invitation->inviter->name,
                'message'           => $invitation->message,
                'invited_at'        => $invitation->created_at->toISOString(),
                'expires_at'        => $invitation->expires_at?->toISOString(),
            ]);

        // Browse courses - published public courses (excluding enrolled and invited)
        $enrolledCourseIds = $user->enrollments()->pluck('course_id')->toArray();
        $invitedCourseIds  = $user->pendingInvitations()->pluck('course_id')->toArray();
        $excludeIds        = array_merge($enrolledCourseIds, $invitedCourseIds);

        $browseCourses = Course::query()
            ->published()
            ->visible()
            ->when(count($excludeIds) > 0, fn($q) => $q->whereNotIn('id', $excludeIds))
            ->with(['user:id,name', 'category:id,name'])
            ->withCount('enrollments')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(fn($course) => [
                'id'                => $course->id,
                'title'             => $course->title,
                'slug'              => $course->slug,
                'short_description' => $course->short_description,
                'thumbnail_path'    => $course->thumbnail_url,
                'difficulty_level'  => $course->difficulty_level,
                'duration'          => $course->duration,
                'instructor'        => $course->user->name,
                'category'          => $course->category?->name,
                'enrollments_count' => $course->enrollments_count,
            ]);

        return Inertia::render('learner/Dashboard', [
            'featuredCourses' => $featuredCourses,
            'myLearning'      => $myLearning,
            'invitedCourses'  => $invitedCourses,
            'browseCourses'   => $browseCourses,
        ]);
    }
}