<?php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class MultipleChoiceGradingStrategy implements GradingStrategyContract
{
    public function supports(Question $question): bool
    {
        return in_array($question->question_type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['multiple_choice', 'single_choice'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        // $answer can be a single ID or array of IDs
        $selectedIds = is_array($answer) ? $answer : [$answer];
        $selectedIds = array_map('intval', $selectedIds);

        $correctOptionIds = $question->options()
            ->where('is_correct', true)
            ->pluck('id')
            ->toArray();

        // Sort for comparison
        sort($selectedIds);
        sort($correctOptionIds);

        $isCorrect = $selectedIds === $correctOptionIds;

        if ($isCorrect) {
            return GradingResult::correct(
                points: $question->points,
                feedback: 'Jawaban benar!'
            );
        }

        // Partial credit for multiple choice with multiple correct answers
        if ($question->question_type === 'multiple_choice' && count($selectedIds) > 0 && count($correctOptionIds) > 1) {
            $correctSelected = count(array_intersect($selectedIds, $correctOptionIds));
            $totalCorrect = count($correctOptionIds);
            $incorrectSelected = count($selectedIds) - $correctSelected;

            // Partial score: correct answers minus penalties for wrong answers
            $partialScore = max(0, ($correctSelected - $incorrectSelected * 0.5) / $totalCorrect * $question->points);

            if ($partialScore > 0) {
                return GradingResult::partial(
                    score: round($partialScore, 2),
                    maxScore: $question->points,
                    feedback: "Sebagian benar. {$correctSelected} dari {$totalCorrect} jawaban benar."
                );
            }
        }

        return GradingResult::incorrect(
            maxPoints: $question->points,
            feedback: 'Jawaban salah.'
        );
    }
}
