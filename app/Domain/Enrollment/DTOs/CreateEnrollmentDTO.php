<?php

namespace App\Domain\Enrollment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use DateTimeImmutable;
use DateTimeInterface;

final class CreateEnrollmentDTO extends DataTransferObject
{
    public function __construct(
        public int $userId,
        public int $courseId,
        public ?int $invitedBy = null,
        public ?DateTimeInterface $enrolledAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            userId: $data['user_id'],
            courseId: $data['course_id'],
            invitedBy: $data['invited_by'] ?? null,
            enrolledAt: isset($data['enrolled_at'])
                ? new DateTimeImmutable($data['enrolled_at'])
                : null,
        );
    }
}
