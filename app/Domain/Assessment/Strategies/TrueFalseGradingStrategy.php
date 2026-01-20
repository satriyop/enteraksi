<?php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class TrueFalseGradingStrategy implements GradingStrategyContract
{
    /** @var array<string> */
    protected array $trueValues = ['true', 'benar', '1', 'ya', 'yes'];

    /** @var array<string> */
    protected array $falseValues = ['false', 'salah', '0', 'tidak', 'no'];

    public function supports(Question $question): bool
    {
        return in_array($question->question_type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['true_false', 'boolean'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        $normalizedAnswer = $this->normalizeAnswer($answer);
        $correctAnswer = $this->getCorrectAnswer($question);

        if ($normalizedAnswer === null) {
            return GradingResult::incorrect(
                maxPoints: $question->points,
                feedback: 'Jawaban tidak valid.'
            );
        }

        $isCorrect = $normalizedAnswer === $correctAnswer;

        if ($isCorrect) {
            return GradingResult::correct(
                points: $question->points,
                feedback: $correctAnswer ? 'Benar! Pernyataan ini benar.' : 'Benar! Pernyataan ini salah.'
            );
        }

        return GradingResult::incorrect(
            maxPoints: $question->points,
            feedback: $correctAnswer ? 'Jawaban salah. Pernyataan ini sebenarnya benar.' : 'Jawaban salah. Pernyataan ini sebenarnya salah.'
        );
    }

    protected function normalizeAnswer(mixed $answer): ?bool
    {
        if (is_bool($answer)) {
            return $answer;
        }

        $normalized = strtolower(trim((string) $answer));

        if (in_array($normalized, $this->trueValues)) {
            return true;
        }

        if (in_array($normalized, $this->falseValues)) {
            return false;
        }

        return null;
    }

    protected function getCorrectAnswer(Question $question): bool
    {
        // Check options for correct answer
        $correctOption = $question->options()->where('is_correct', true)->first();

        if ($correctOption) {
            return $this->normalizeAnswer($correctOption->option_text) ?? true;
        }

        // Fallback to correct_answer field if exists
        if (isset($question->correct_answer)) {
            return (bool) $question->correct_answer;
        }

        return true;
    }
}
