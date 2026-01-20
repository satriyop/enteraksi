<?php

namespace App\Domain\Assessment\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class MaxAttemptsReachedException extends DomainException
{
    public function __construct(
        int $userId,
        int $assessmentId,
        int $maxAttempts,
        int $currentAttempts
    ) {
        parent::__construct(
            "User has reached maximum attempts ({$maxAttempts}) for this assessment",
            [
                'user_id' => $userId,
                'assessment_id' => $assessmentId,
                'max_attempts' => $maxAttempts,
                'current_attempts' => $currentAttempts,
            ]
        );
    }
}
