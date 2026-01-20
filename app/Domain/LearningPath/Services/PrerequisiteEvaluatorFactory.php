<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\Contracts\PrerequisiteEvaluatorContract;
use App\Domain\LearningPath\Strategies\ImmediatePreviousPrerequisiteEvaluator;
use App\Domain\LearningPath\Strategies\NoPrerequisiteEvaluator;
use App\Domain\LearningPath\Strategies\SequentialPrerequisiteEvaluator;
use App\Models\LearningPath;
use InvalidArgumentException;

class PrerequisiteEvaluatorFactory
{
    /**
     * @var array<string, class-string<PrerequisiteEvaluatorContract>>
     */
    private array $evaluators = [
        'sequential' => SequentialPrerequisiteEvaluator::class,
        'immediate_previous' => ImmediatePreviousPrerequisiteEvaluator::class,
        'none' => NoPrerequisiteEvaluator::class,
    ];

    /**
     * Create the appropriate evaluator for a learning path.
     */
    public function make(LearningPath $path): PrerequisiteEvaluatorContract
    {
        $type = $path->prerequisite_mode ?? config('lms.learning_path.default_prerequisite_mode', 'sequential');

        return $this->resolve($type);
    }

    /**
     * Resolve an evaluator by type name.
     */
    public function resolve(string $type): PrerequisiteEvaluatorContract
    {
        if (! isset($this->evaluators[$type])) {
            throw new InvalidArgumentException("Unknown prerequisite evaluator type: {$type}");
        }

        return app($this->evaluators[$type]);
    }

    /**
     * Get all available evaluator types.
     *
     * @return array<string, string>
     */
    public function getAvailableTypes(): array
    {
        return [
            'sequential' => 'Berurutan (Semua sebelumnya harus selesai)',
            'immediate_previous' => 'Hanya kursus sebelumnya',
            'none' => 'Tanpa prasyarat',
        ];
    }

    /**
     * Register a custom evaluator.
     */
    public function register(string $type, string $evaluatorClass): void
    {
        if (! is_a($evaluatorClass, PrerequisiteEvaluatorContract::class, true)) {
            throw new InvalidArgumentException(
                'Evaluator class must implement PrerequisiteEvaluatorContract'
            );
        }

        $this->evaluators[$type] = $evaluatorClass;
    }
}
