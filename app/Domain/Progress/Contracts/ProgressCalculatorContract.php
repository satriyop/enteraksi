<?php

namespace App\Domain\Progress\Contracts;

use App\Models\Enrollment;

interface ProgressCalculatorContract
{
    /**
     * Calculate progress for an enrollment.
     *
     * @return float Progress percentage (0-100)
     */
    public function calculate(Enrollment $enrollment): float;

    /**
     * Determine if the enrollment is complete.
     */
    public function isComplete(Enrollment $enrollment): bool;

    /**
     * Get the name of this calculator strategy.
     */
    public function getName(): string;
}
