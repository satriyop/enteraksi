<?php

namespace App\Domain\Progress\Strategies;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\LearningPathEnrollment;
use Illuminate\Support\Collection;

/**
 * Progress calculator that includes both lessons and assessments.
 *
 * Calculation Formula:
 * - Total Progress = (Lesson Progress × 70%) + (Assessment Progress × 30%)
 *
 * Lesson Progress:
 * - Completed lessons ÷ Total lessons × 100
 * - Only counts existing (non-deleted) lessons
 *
 * Assessment Progress:
 * - Passed required assessments ÷ Total required assessments × 100
 * - Only counts PUBLISHED assessments with is_required=true
 * - A passed attempt (passed=true) counts the assessment as complete
 *
 * Completion Criteria:
 * - ALL lessons must be completed
 * - ALL required assessments must have at least one passed attempt
 * - Optional assessments (is_required=false) do NOT block completion
 *
 * @see \Tests\Unit\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculatorTest
 */
class AssessmentInclusiveProgressCalculator implements ProgressCalculatorContract
{
    protected float $lessonWeight = 0.7;

    protected float $assessmentWeight = 0.3;

    public function calculateCourseProgress(Enrollment $enrollment): float
    {
        $lessonProgress = $this->calculateLessonProgress($enrollment);
        $assessmentProgress = $this->calculateAssessmentProgress($enrollment);

        return round(
            ($lessonProgress * $this->lessonWeight) +
            ($assessmentProgress * $this->assessmentWeight),
            1
        );
    }

    public function isCourseComplete(Enrollment $enrollment): bool
    {
        // All lessons completed (only count lessons that still exist)
        $totalLessons = $enrollment->course->lessons()->count();
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->whereHas('lesson')
            ->count();

        if ($totalLessons > 0 && $completedLessons < $totalLessons) {
            return false;
        }

        // Get required assessments with their passed attempts in a single query
        $requiredAssessmentIds = Assessment::query()
            ->where('course_id', $enrollment->course_id)
            ->published()
            ->where('is_required', true)
            ->pluck('id');

        // If no required assessments exist, the course is complete if lessons are done
        if ($requiredAssessmentIds->isEmpty()) {
            return true;
        }

        // Batch load all passed attempts for required assessments
        $passedAssessmentIds = $this->getPassedAssessmentIds(
            $enrollment->user_id,
            $requiredAssessmentIds
        );

        // All required assessments must have at least one passed attempt
        return $requiredAssessmentIds->diff($passedAssessmentIds)->isEmpty();
    }

    public function getName(): string
    {
        return 'assessment_inclusive';
    }

    /**
     * Get assessment statistics for progress visibility.
     *
     * @return array{total: int, passed: int, pending: int, required_total: int, required_passed: int}
     */
    public function getAssessmentStats(Enrollment $enrollment): array
    {
        // Get all published assessments in one query
        $assessments = Assessment::query()
            ->where('course_id', $enrollment->course_id)
            ->published()
            ->select('id', 'is_required')
            ->get();

        if ($assessments->isEmpty()) {
            return [
                'total' => 0,
                'passed' => 0,
                'pending' => 0,
                'required_total' => 0,
                'required_passed' => 0,
            ];
        }

        // Batch load all passed attempts for this user
        $passedAssessmentIds = $this->getPassedAssessmentIds(
            $enrollment->user_id,
            $assessments->pluck('id')
        );

        $requiredAssessments = $assessments->where('is_required', true);
        $requiredPassedCount = $requiredAssessments
            ->whereIn('id', $passedAssessmentIds)
            ->count();

        return [
            'total' => $assessments->count(),
            'passed' => $passedAssessmentIds->count(),
            'pending' => $assessments->count() - $passedAssessmentIds->count(),
            'required_total' => $requiredAssessments->count(),
            'required_passed' => $requiredPassedCount,
        ];
    }

    protected function calculateLessonProgress(Enrollment $enrollment): float
    {
        $totalLessons = $enrollment->course->lessons()->count();

        if ($totalLessons === 0) {
            return 100; // No lessons = 100% lesson progress
        }

        // Only count completions for lessons that still exist
        $completedLessons = $enrollment->lessonProgress()
            ->where('is_completed', true)
            ->whereHas('lesson')
            ->count();

        return ($completedLessons / $totalLessons) * 100;
    }

    protected function calculateAssessmentProgress(Enrollment $enrollment): float
    {
        // Only count REQUIRED assessments for progress (consistent with isComplete)
        $requiredAssessmentIds = Assessment::query()
            ->where('course_id', $enrollment->course_id)
            ->published()
            ->where('is_required', true)
            ->pluck('id');

        if ($requiredAssessmentIds->isEmpty()) {
            return 100; // No required assessments = 100% assessment progress
        }

        // Batch load all passed attempts for required assessments
        $passedCount = $this->getPassedAssessmentIds(
            $enrollment->user_id,
            $requiredAssessmentIds
        )->count();

        return ($passedCount / $requiredAssessmentIds->count()) * 100;
    }

    /**
     * Get IDs of assessments that the user has passed.
     *
     * Uses a single query instead of N+1 queries per assessment.
     */
    protected function getPassedAssessmentIds(int $userId, Collection $assessmentIds): Collection
    {
        if ($assessmentIds->isEmpty()) {
            return collect();
        }

        return AssessmentAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('assessment_id', $assessmentIds)
            ->where('passed', true)
            ->distinct()
            ->pluck('assessment_id');
    }

    public function calculatePathProgress(LearningPathEnrollment $enrollment): float
    {
        $learningPath = $enrollment->learningPath;

        // Get only required courses
        $requiredCourses = $learningPath->courses()
            ->wherePivot('is_required', true)
            ->get();

        if ($requiredCourses->isEmpty()) {
            return 0;
        }

        $totalLessonProgress = 0;
        $totalAssessmentProgress = 0;
        $courseCount = $requiredCourses->count();

        foreach ($requiredCourses as $course) {
            // Get course enrollment for this user
            $courseEnrollment = Enrollment::query()
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $course->id)
                ->first();

            if ($courseEnrollment) {
                $totalLessonProgress += $this->calculateLessonProgress($courseEnrollment);
                $totalAssessmentProgress += $this->calculateAssessmentProgress($courseEnrollment);
            }
        }

        $avgLessonProgress = $courseCount > 0 ? $totalLessonProgress / $courseCount : 0;
        $avgAssessmentProgress = $courseCount > 0 ? $totalAssessmentProgress / $courseCount : 0;

        return round(
            ($avgLessonProgress * $this->lessonWeight) +
            ($avgAssessmentProgress * $this->assessmentWeight),
            1
        );
    }

    public function isPathComplete(LearningPathEnrollment $enrollment): bool
    {
        $learningPath = $enrollment->learningPath;

        // Get only required courses
        $requiredCourses = $learningPath->courses()
            ->wherePivot('is_required', true)
            ->get();

        foreach ($requiredCourses as $course) {
            // Get course enrollment for this user
            $courseEnrollment = Enrollment::query()
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $course->id)
                ->first();

            if (! $courseEnrollment || ! $this->isCourseComplete($courseEnrollment)) {
                return false;
            }
        }

        return true;
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
