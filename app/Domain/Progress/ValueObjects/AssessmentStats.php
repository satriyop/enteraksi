<?php

namespace App\Domain\Progress\ValueObjects;

/**
 * Assessment completion statistics for course progress visibility.
 *
 * Helps learners understand why they may not be at 100% completion
 * when all lessons are done but assessments remain.
 */
final readonly class AssessmentStats
{
    public function __construct(
        /** Total number of published assessments */
        public int $total,
        /** Number of passed assessments */
        public int $passed,
        /** Number of pending (not yet passed) assessments */
        public int $pending,
        /** Number of required assessments */
        public int $requiredTotal,
        /** Number of required assessments that are passed */
        public int $requiredPassed,
    ) {}

    /**
     * Create an empty stats object for courses without assessments.
     */
    public static function empty(): self
    {
        return new self(
            total: 0,
            passed: 0,
            pending: 0,
            requiredTotal: 0,
            requiredPassed: 0,
        );
    }

    /**
     * Create from the calculator's stats array.
     *
     * @param  array{total: int, passed: int, pending: int, required_total: int, required_passed: int}  $stats
     */
    public static function fromArray(array $stats): static
    {
        return new self(
            total: $stats['total'],
            passed: $stats['passed'],
            pending: $stats['pending'],
            requiredTotal: $stats['required_total'],
            requiredPassed: $stats['required_passed'],
        );
    }

    /**
     * Check if all required assessments are passed.
     */
    public function allRequiredPassed(): bool
    {
        return $this->requiredTotal === 0 || $this->requiredPassed >= $this->requiredTotal;
    }

    /**
     * Get number of required assessments still pending.
     */
    public function requiredPending(): int
    {
        return max(0, $this->requiredTotal - $this->requiredPassed);
    }

    /**
     * Check if there are any assessments at all.
     */
    public function hasAssessments(): bool
    {
        return $this->total > 0;
    }

    public function toResponse(): array
    {
        return [
            'total' => $this->total,
            'passed' => $this->passed,
            'pending' => $this->pending,
            'required_total' => $this->requiredTotal,
            'required_passed' => $this->requiredPassed,
            'required_pending' => $this->requiredPending(),
            'all_required_passed' => $this->allRequiredPassed(),
        ];
    }
}
