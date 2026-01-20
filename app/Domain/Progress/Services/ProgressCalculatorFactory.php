<?php

namespace App\Domain\Progress\Services;

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Domain\Progress\Strategies\WeightedProgressCalculator;
use App\Models\Course;
use Illuminate\Contracts\Container\Container;

class ProgressCalculatorFactory
{
    public function __construct(
        protected Container $container
    ) {}

    /**
     * Get the appropriate calculator for a course.
     */
    public function forCourse(Course $course): ProgressCalculatorContract
    {
        // Course can specify its calculator type via progress_calculator_type attribute
        $calculatorType = $course->progress_calculator_type
            ?? config('lms.progress_calculator', 'lesson_based');

        return $this->resolve($calculatorType);
    }

    /**
     * Resolve calculator by type name.
     */
    public function resolve(string $type): ProgressCalculatorContract
    {
        return match ($type) {
            'weighted' => $this->container->make(WeightedProgressCalculator::class),
            'assessment_inclusive' => $this->container->make(AssessmentInclusiveProgressCalculator::class),
            default => $this->container->make(LessonBasedProgressCalculator::class),
        };
    }

    /**
     * Get the default calculator based on configuration.
     */
    public function getDefault(): ProgressCalculatorContract
    {
        return $this->resolve(config('lms.progress_calculator', 'lesson_based'));
    }

    /**
     * Get all available calculator types.
     *
     * @return array<string, string>
     */
    public function getAvailableTypes(): array
    {
        return [
            'lesson_based' => 'Berbasis Pelajaran',
            'weighted' => 'Berbasis Durasi (Tertimbang)',
            'assessment_inclusive' => 'Termasuk Penilaian',
        ];
    }
}
