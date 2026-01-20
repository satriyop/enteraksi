<?php

namespace App\Domain\Assessment\Contracts;

use App\Models\Question;
use Illuminate\Support\Collection;

interface GradingStrategyResolverContract
{
    /**
     * Resolve the appropriate strategy for a question.
     */
    public function resolve(Question $question): ?GradingStrategyContract;

    /**
     * Get all registered strategies.
     *
     * @return Collection<int, GradingStrategyContract>
     */
    public function getAllStrategies(): Collection;

    /**
     * Get all supported question types.
     *
     * @return array<string>
     */
    public function getSupportedTypes(): array;
}
