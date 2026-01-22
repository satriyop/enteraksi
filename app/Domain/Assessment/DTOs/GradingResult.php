<?php

namespace App\Domain\Assessment\DTOs;

final readonly class GradingResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $isCorrect,
        public float $score,
        public float $maxScore,
        public ?string $feedback = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array{
     *     is_correct: bool,
     *     score: float|int,
     *     max_score: float|int,
     *     feedback?: string|null,
     *     metadata?: array<string, mixed>
     * }  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            isCorrect: $data['is_correct'],
            score: $data['score'],
            maxScore: $data['max_score'],
            feedback: $data['feedback'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public static function correct(float $points, ?string $feedback = null): static
    {
        return new static(
            isCorrect: true,
            score: $points,
            maxScore: $points,
            feedback: $feedback,
        );
    }

    public static function incorrect(float $maxPoints, ?string $feedback = null): static
    {
        return new static(
            isCorrect: false,
            score: 0,
            maxScore: $maxPoints,
            feedback: $feedback,
        );
    }

    public static function partial(float $score, float $maxScore, ?string $feedback = null, array $metadata = []): static
    {
        return new static(
            isCorrect: $score > 0,
            score: $score,
            maxScore: $maxScore,
            feedback: $feedback,
            metadata: $metadata,
        );
    }

    public function getPercentage(): float
    {
        return $this->maxScore > 0
            ? round(($this->score / $this->maxScore) * 100, 2)
            : 0;
    }
}
