<?php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;
use App\Models\LearningPathEnrollment;

class LessonBasedProgressCalculator implements ProgressCalculatorContract
{
    public function calculateCourseProgress(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 0;
        }

        // Only count completions for lessons that still exist (not soft-deleted)
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->whereHas('lesson')
            ->count();

        return round(($completedLessons / $totalLessons) * 100, 1);
    }

    public function isCourseComplete(Enrollment $enrollment): bool
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return false;
        }

        // Only count completions for lessons that still exist (not soft-deleted)
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->whereHas('lesson')
            ->count();

        return $completedLessons >= $totalLessons;
    }

    public function calculatePathProgress(LearningPathEnrollment $enrollment): float
    {
        $learningPath = $enrollment->learningPath;

        // Get only required courses (is_required = true)
        $requiredCourses = $learningPath->courses()
            ->wherePivot('is_required', true)
            ->get();

        if ($requiredCourses->isEmpty()) {
            return 0;
        }

        $totalRequiredCourses = $requiredCourses->count();
        $completedRequiredCourses = 0;

        foreach ($requiredCourses as $course) {
            // Check if user has completed this course
            $courseEnrollment = Enrollment::query()
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->first();

            if ($courseEnrollment) {
                $completedRequiredCourses++;
            }
        }

        return round(($completedRequiredCourses / $totalRequiredCourses) * 100, 1);
    }

    public function isPathComplete(LearningPathEnrollment $enrollment): bool
    {
        $learningPath = $enrollment->learningPath;

        // Get only required courses (is_required = true)
        $requiredCourses = $learningPath->courses()
            ->wherePivot('is_required', true)
            ->get();

        if ($requiredCourses->isEmpty()) {
            return false;
        }

        foreach ($requiredCourses as $course) {
            // Check if user has completed this course
            $courseEnrollment = Enrollment::query()
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->first();

            if (! $courseEnrollment) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return 'lesson_based';
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
