<?php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Enrollment;

class AssessmentInclusiveProgressCalculator implements ProgressCalculatorContract
{
    protected float $lessonWeight = 0.7;

    protected float $assessmentWeight = 0.3;

    public function calculate(Enrollment $enrollment): float
    {
        $lessonProgress = $this->calculateLessonProgress($enrollment);
        $assessmentProgress = $this->calculateAssessmentProgress($enrollment);

        return round(
            ($lessonProgress * $this->lessonWeight) +
            ($assessmentProgress * $this->assessmentWeight),
            1
        );
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        // All lessons completed
        $totalLessons = $enrollment->course->lessons()->count();
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        if ($totalLessons > 0 && $completedLessons < $totalLessons) {
            return false;
        }

        // All required assessments passed
        $requiredAssessments = $enrollment->course->assessments()
            ->published()
            ->where('is_required', true)
            ->get();

        // If no required assessments exist, the course is complete if lessons are done
        if ($requiredAssessments->isEmpty()) {
            return true;
        }

        foreach ($requiredAssessments as $assessment) {
            $hasPassed = $assessment->attempts()
                ->where('user_id', $enrollment->user_id)
                ->where('passed', true)
                ->exists();

            if (! $hasPassed) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return 'assessment_inclusive';
    }

    protected function calculateLessonProgress(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 100; // No lessons = 100% lesson progress
        }

        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->count();

        return ($completedLessons / $totalLessons) * 100;
    }

    protected function calculateAssessmentProgress(Enrollment $enrollment): float
    {
        $assessments = $enrollment->course->assessments()->published()->get();

        if ($assessments->isEmpty()) {
            return 100; // No assessments = 100% assessment progress
        }

        $passedCount = 0;

        foreach ($assessments as $assessment) {
            $hasPassed = $assessment->attempts()
                ->where('user_id', $enrollment->user_id)
                ->where('passed', true)
                ->exists();

            if ($hasPassed) {
                $passedCount++;
            }
        }

        return ($passedCount / $assessments->count()) * 100;
    }
}
