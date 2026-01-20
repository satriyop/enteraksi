<?php

namespace App\Domain\Assessment\Contracts;

use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

interface GradingStrategyContract
{
    /**
     * Check if this strategy can grade the given question type.
     */
    public function supports(Question $question): bool;

    /**
     * Grade the answer and return a result.
     */
    public function grade(Question $question, mixed $answer): GradingResult;

    /**
     * Get the question types this strategy handles.
     *
     * @return array<string>
     */
    public function getHandledTypes(): array;
}
