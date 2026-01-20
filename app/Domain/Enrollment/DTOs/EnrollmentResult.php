<?php

namespace App\Domain\Enrollment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use App\Models\Enrollment;

final class EnrollmentResult extends DataTransferObject
{
    public function __construct(
        public Enrollment $enrollment,
        public bool $isNewEnrollment,
        public ?string $message = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            enrollment: $data['enrollment'],
            isNewEnrollment: $data['is_new_enrollment'],
            message: $data['message'] ?? null,
        );
    }
}
