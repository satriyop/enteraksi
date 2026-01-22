<?php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

class WeightedProgressCalculator implements ProgressCalculatorContract
{
    public function calculate(Enrollment $enrollment): float
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

    public function isComplete(Enrollment $enrollment): bool
    {
        return $this->calculate($enrollment) >= 100;
    }

    public function getName(): string
    {
        return 'weighted';
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
}
