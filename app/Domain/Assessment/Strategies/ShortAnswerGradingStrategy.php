<?php

namespace App\Domain\Assessment\Strategies;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

class ShortAnswerGradingStrategy implements GradingStrategyContract
{
    public function supports(Question $question): bool
    {
        return in_array($question->question_type, $this->getHandledTypes());
    }

    public function getHandledTypes(): array
    {
        return ['short_answer', 'fill_blank'];
    }

    public function grade(Question $question, mixed $answer): GradingResult
    {
        $answer = trim((string) $answer);

        if (empty($answer)) {
            return GradingResult::incorrect(
                maxPoints: $question->points,
                feedback: 'Tidak ada jawaban.'
            );
        }

        $acceptableAnswers = $this->getAcceptableAnswers($question);

        if (empty($acceptableAnswers)) {
            // No acceptable answers defined - requires manual grading
            return GradingResult::partial(
                score: 0,
                maxScore: $question->points,
                feedback: 'Memerlukan penilaian manual.',
                metadata: ['requires_manual_grading' => true]
            );
        }

        // Check against acceptable answers
        $caseSensitive = $question->case_sensitive ?? false;
        foreach ($acceptableAnswers as $acceptable) {
            if ($this->matchesAnswer($answer, $acceptable, $caseSensitive)) {
                return GradingResult::correct(
                    points: $question->points,
                    feedback: 'Jawaban benar!'
                );
            }
        }

        // Check for partial matches (fuzzy matching)
        $similarityThreshold = config('lms.grading.short_answer_similarity_threshold', 0.8);
        $bestMatch = $this->findBestMatch($answer, $acceptableAnswers);

        if ($bestMatch !== null && $bestMatch['similarity'] >= $similarityThreshold) {
            return GradingResult::partial(
                score: $question->points * $bestMatch['similarity'],
                maxScore: $question->points,
                feedback: "Hampir benar. Jawaban yang diharapkan: \"{$bestMatch['answer']}\""
            );
        }

        return GradingResult::incorrect(
            maxPoints: $question->points,
            feedback: 'Jawaban salah.'
        );
    }

    /**
     * @return array<string>
     */
    protected function getAcceptableAnswers(Question $question): array
    {
        $answers = [];

        // Check correct_answer field if exists (comma-separated)
        if (isset($question->correct_answer) && ! empty($question->correct_answer)) {
            $answers = array_map('trim', explode(',', $question->correct_answer));
        }

        // Also check options marked as correct
        $correctOptions = $question->options()
            ->where('is_correct', true)
            ->pluck('option_text')
            ->toArray();

        return array_unique(array_filter(array_merge($answers, $correctOptions)));
    }

    protected function matchesAnswer(string $answer, string $acceptable, bool $caseSensitive): bool
    {
        if ($caseSensitive) {
            return $answer === $acceptable;
        }

        return strtolower($answer) === strtolower($acceptable);
    }

    /**
     * @param  array<string>  $acceptableAnswers
     * @return array{answer: string, similarity: float}|null
     */
    protected function findBestMatch(string $answer, array $acceptableAnswers): ?array
    {
        $bestSimilarity = 0;
        $bestAnswer = null;

        foreach ($acceptableAnswers as $acceptable) {
            similar_text(
                strtolower($answer),
                strtolower($acceptable),
                $percent
            );
            $similarity = $percent / 100;

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestAnswer = $acceptable;
            }
        }

        if ($bestAnswer !== null) {
            return [
                'answer' => $bestAnswer,
                'similarity' => $bestSimilarity,
            ];
        }

        return null;
    }
}
