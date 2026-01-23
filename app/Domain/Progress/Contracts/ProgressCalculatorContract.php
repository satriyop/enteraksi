<?php

namespace App\Domain\Progress\Contracts;

use App\Models\Enrollment;
use App\Models\LearningPathEnrollment;

interface ProgressCalculatorContract
{
    // Course progress methods
    /**
     * Calculate progress for a course enrollment.
     *
     * @return float Progress percentage (0-100)
     */
    public function calculateCourseProgress(Enrollment $enrollment): float;

    /**
     * Determine if the course enrollment is complete.
     */
    public function isCourseComplete(Enrollment $enrollment): bool;

    // Learning path progress methods
    /**
     * Calculate progress for a learning path enrollment.
     *
     * Only considers required courses, not optional ones.
     *
     * @return float Progress percentage (0-100)
     */
    public function calculatePathProgress(LearningPathEnrollment $enrollment): float;

    /**
     * Determine if the learning path enrollment is complete.
     *
     * Only considers required courses, not optional ones.
     */
    public function isPathComplete(LearningPathEnrollment $enrollment): bool;

    /**
     * Get the name of this calculator strategy.
     */
    public function getName(): string;

    // Legacy methods for backward compatibility
    public function calculate(Enrollment $enrollment): float;

    public function isComplete(Enrollment $enrollment): bool;
}
