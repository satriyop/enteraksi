<?php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;
use App\Models\LearningPathEnrollment;
use Illuminate\Support\Facades\DB;

class WeightedProgressCalculator implements ProgressCalculatorContract
{
    public function calculateCourseProgress(Enrollment $enrollment): float
    {
        $courseId = $enrollment->course_id;

        // Get total weighted duration (using lessons through course_sections)
        // Exclude soft-deleted lessons
        $totalWeight = DB::table('lessons')
            ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
            ->where('course_sections.course_id', $courseId)
            ->whereNull('lessons.deleted_at')
            ->sum('lessons.estimated_duration_minutes');

        // Fallback to 1 if no duration set to avoid division by zero
        if ($totalWeight === 0) {
            // Fall back to simple lesson count
            return $this->calculateByLessonCount($enrollment);
        }

        // Get completed weighted duration (only for lessons that still exist)
        $completedWeight = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
            ->where('course_sections.course_id', $courseId)
            ->where('lesson_progress.enrollment_id', $enrollment->id)
            ->where('lesson_progress.is_completed', true)
            ->whereNull('lessons.deleted_at')
            ->sum('lessons.estimated_duration_minutes');

        return round(($completedWeight / $totalWeight) * 100, 1);
    }

    public function isCourseComplete(Enrollment $enrollment): bool
    {
        return $this->calculateCourseProgress($enrollment) >= 100;
    }

    public function calculatePathProgress(LearningPathEnrollment $enrollment): float
    {
        $learningPath = $enrollment->learningPath;

        // Get only required courses with their total weighted duration
        $requiredCourses = $learningPath->courses()
            ->wherePivot('is_required', true)
            ->get();

        if ($requiredCourses->isEmpty()) {
            return 0;
        }

        $totalWeight = 0;
        $completedWeight = 0;

        foreach ($requiredCourses as $course) {
            // Calculate course's total weight
            $courseWeight = DB::table('lessons')
                ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
                ->where('course_sections.course_id', $course->id)
                ->whereNull('lessons.deleted_at')
                ->sum('lessons.estimated_duration_minutes');

            $totalWeight += $courseWeight;

            // Check if course is completed and add its weight
            $courseEnrollment = Enrollment::query()
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->first();

            if ($courseEnrollment) {
                $completedWeight += $courseWeight;
            }
        }

        if ($totalWeight === 0) {
            return 0;
        }

        return round(($completedWeight / $totalWeight) * 100, 1);
    }

    public function isPathComplete(LearningPathEnrollment $enrollment): bool
    {
        return $this->calculatePathProgress($enrollment) >= 100;
    }

    /**
     * Fallback calculation by lesson count when no duration data is available.
     */
    protected function calculateByLessonCount(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 0;
        }

        // Only count completions for lessons that still exist
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->whereHas('lesson')
            ->count();

        return round(($completedLessons / $totalLessons) * 100, 1);
    }

    public function getName(): string
    {
        return 'weighted';
    }

    // Legacy methods for backward compatibility
    public function calculate(Enrollment $enrollment): float
    {
        return $this->calculateCourseProgress($enrollment);
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        return $this->isCourseComplete($enrollment);
    }
}
