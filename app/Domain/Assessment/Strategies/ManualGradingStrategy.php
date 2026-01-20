<?php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class ManualGradingStrategy implements GradingStrategyContract
{
    public function supports(Question $question): bool
    {
        return in_array($question->question_type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['essay', 'long_answer', 'file_upload', 'code', 'matching'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        // Manual grading types always return "pending" result
        return new GradingResult(
            isCorrect: false, // Unknown until manually graded
            score: 0, // Will be set by grader
            maxScore: $question->points,
            feedback: 'Menunggu penilaian instruktur.',
            metadata: [
                'requires_manual_grading' => true,
                'grading_rubric' => $question->grading_rubric ?? null,
            ]
        );
    }
}
