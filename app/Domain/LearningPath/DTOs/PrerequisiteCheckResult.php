<?php

namespace App\Domain\LearningPath\DTOs;

final readonly class PrerequisiteCheckResult
{
    /**
     * @param  array<int, array{id: int, title: string}>  $missingPrerequisites
     */
    public function __construct(
        public bool $isMet,
        public array $missingPrerequisites = [],
        public ?string $reason = null,
    ) {}

    public static function met(): self
    {
        return new self(isMet: true);
    }

    public static function notMet(array $missing, string $reason): self
    {
        return new self(
            isMet: false,
            missingPrerequisites: $missing,
            reason: $reason,
        );
    }

    public static function fromArray(array $data): static
    {
        return new self(
            isMet: $data['is_met'],
            missingPrerequisites: $data['missing_prerequisites'] ?? [],
            reason: $data['reason'] ?? null,
        );
    }

    public function toResponse(): array
    {
        return [
            'is_met' => $this->isMet,
            'missing_prerequisites' => $this->missingPrerequisites,
            'reason' => $this->reason,
        ];
    }

    /**
     * Get formatted missing course titles.
     */
    public function getMissingTitles(): string
    {
        return implode(', ', array_column($this->missingPrerequisites, 'title'));
    }
}
