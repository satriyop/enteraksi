<?php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;

class LessonBasedProgressCalculator implements ProgressCalculatorContract
{
    public function calculate(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 0;
        }

        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return round(($completedLessons / $totalLessons) * 100, 1);
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return false;
        }

        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return $completedLessons >= $totalLessons;
    }

    public function getName(): string
    {
        return 'lesson_based';
    }
}
