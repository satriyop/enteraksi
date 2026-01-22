<?php

namespace App\Domain\Assessment\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class MaxAttemptsReachedException extends DomainException
{
    public function __construct(
        int $userId,
        int $assessmentId,
        int $maxAttempts,
        int $completedAttempts
    ) {
        parent::__construct(
            "User {$userId} has reached maximum attempts ({$completedAttempts}/{$maxAttempts}) for assessment {$assessmentId}",
            [
                'user_id' => $userId,
                'assessment_id' => $assessmentId,
                'max_attempts' => $maxAttempts,
                'completed_attempts' => $completedAttempts,
            ]
        );
    }
}
