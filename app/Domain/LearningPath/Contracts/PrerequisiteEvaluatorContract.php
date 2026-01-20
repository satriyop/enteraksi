<?php

namespace App\Domain\LearningPath\Contracts;

use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\LearningPathEnrollment;

interface PrerequisiteEvaluatorContract
{
    /**
     * Evaluate if prerequisites are met for a course in the learning path.
     */
    public function evaluate(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult;

    /**
     * Get the evaluator's name identifier.
     */
    public function getName(): string;
}
