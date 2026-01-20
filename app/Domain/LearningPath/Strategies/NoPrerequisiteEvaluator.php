<?php

namespace App\Domain\LearningPath\Strategies;

use App\Domain\LearningPath\Contracts\PrerequisiteEvaluatorContract;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\LearningPathEnrollment;

/**
 * Evaluates prerequisites as always met.
 * All courses in the path are available immediately.
 */
class NoPrerequisiteEvaluator implements PrerequisiteEvaluatorContract
{
    public function evaluate(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        return PrerequisiteCheckResult::met();
    }

    public function getName(): string
    {
        return 'none';
    }
}
