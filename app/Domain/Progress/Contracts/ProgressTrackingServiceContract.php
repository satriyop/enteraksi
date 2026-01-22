<?php

namespace App\Domain\Progress\Contracts;

use App\Domain\Progress\DTOs\ProgressResult;
use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Domain\Progress\ValueObjects\AssessmentStats;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;

interface ProgressTrackingServiceContract
{
    /**
     * Update progress for a lesson.
     */
    public function updateProgress(ProgressUpdateDTO $dto): ProgressResult;

    /**
     * Mark a lesson as completed.
     */
    public function completeLesson(Enrollment $enrollment, Lesson $lesson): ProgressResult;

    /**
     * Get or create progress record for enrollment and lesson.
     */
    public function getOrCreateProgress(Enrollment $enrollment, Lesson $lesson): LessonProgress;

    /**
     * Recalculate course progress for an enrollment.
     */
    public function recalculateCourseProgress(Enrollment $enrollment): float;

    /**
     * Check if all lessons are completed.
     */
    public function isEnrollmentComplete(Enrollment $enrollment): bool;

    /**
     * Get assessment completion statistics for progress visibility.
     */
    public function getAssessmentStats(Enrollment $enrollment): AssessmentStats;
}
