<?php

namespace App\Http\Resources\Progress;

use App\Domain\LearningPath\DTOs\PathProgressResult;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Transforms PathProgressResult for frontend consumption.
 *
 * Uses batch loading to avoid N+1 queries when enriching course data.
 *
 * @mixin PathProgressResult
 */
class PathProgressResource extends JsonResource
{
    /**
     * Disable data wrapping for Inertia compatibility.
     */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $baseResponse = $this->resource->toResponse();
        $courses = $baseResponse['courses'];

        // Batch load all course data in 1 query
        $courseIds = array_column($courses, 'course_id');
        $courseModels = Course::query()
            ->whereIn('id', $courseIds)
            ->withCount('lessons')
            ->get()
            ->keyBy('id');

        // Batch load lesson progress stats for all enrollments in 1 query
        $enrollmentIds = array_filter(array_column($courses, 'enrollment_id'));
        $lessonProgressStats = [];

        if (! empty($enrollmentIds)) {
            $lessonProgressStats = DB::table('lesson_progress')
                ->whereIn('enrollment_id', $enrollmentIds)
                ->selectRaw('enrollment_id,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
                    SUM(time_spent_seconds) as total_time_spent')
                ->groupBy('enrollment_id')
                ->get()
                ->keyBy('enrollment_id');
        }

        // Transform courses using pre-loaded data (0 queries)
        $totalTimeSpent = 0;
        $transformedCourses = array_map(function ($course) use ($courseModels, $lessonProgressStats, &$totalTimeSpent) {
            $courseModel = $courseModels->get($course['course_id']);

            $lessonsCount = $courseModel?->lessons_count ?? 0;
            $completedLessons = 0;
            $timeSpent = 0;

            // Use pre-loaded stats if enrollment exists
            if (isset($course['enrollment_id']) && isset($lessonProgressStats[$course['enrollment_id']])) {
                $stats = $lessonProgressStats[$course['enrollment_id']];
                $completedLessons = (int) $stats->completed_count;
                $timeSpent = ($stats->total_time_spent ?? 0) / 60;
                $totalTimeSpent += $timeSpent;
            }

            return array_merge($course, [
                'course_slug' => $courseModel?->slug ?? '',
                'lessons_count' => $lessonsCount,
                'completed_lessons' => $completedLessons,
                'estimated_duration_minutes' => $courseModel?->manual_duration_minutes ?? $courseModel?->estimated_duration_minutes ?? 0,
                'time_spent_minutes' => (int) $timeSpent,
                'enrollment_id' => $course['enrollment_id'] ?? null,
            ]);
        }, $courses);

        return array_merge($baseResponse, [
            'courses' => $transformedCourses,
            'total_time_spent_minutes' => (int) $totalTimeSpent,
            'completed_at' => null,
        ]);
    }
}
