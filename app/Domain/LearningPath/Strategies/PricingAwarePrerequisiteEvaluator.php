<?php

namespace App\Domain\LearningPath\Strategies;

use App\Domain\LearningPath\Contracts\PrerequisiteEvaluatorContract;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\LearningPathEnrollment;

class PricingAwarePrerequisiteEvaluator implements PrerequisiteEvaluatorContract
{
    /**
     * Evaluate if prerequisites are met for a course in the learning path.
     *
     * Considers pricing requirements based on LMS mode.
     */
    public function evaluate(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        $lmsMode = config('lms.mode');

        // In internal mode, all courses are free
        if ($lmsMode === 'internal') {
            return PrerequisiteCheckResult::met();
        }

        // In commercial mode, check pricing
        if ($course->isPaid()) {
            // For now, assume user can afford (payment logic will be handled elsewhere)
            // In a real implementation, you'd check user's balance/credits
            return PrerequisiteCheckResult::notMet(
                missing: [],
                reason: 'Pembayaran diperlukan untuk kursus berbayar'
            );
        }

        return PrerequisiteCheckResult::met();
    }

    /**
     * Get the evaluator's name identifier.
     */
    public function getName(): string
    {
        return 'pricing_aware';
    }
}
