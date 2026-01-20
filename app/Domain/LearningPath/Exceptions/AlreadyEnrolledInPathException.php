<?php

namespace App\Domain\LearningPath\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class AlreadyEnrolledInPathException extends DomainException
{
    public function __construct(int $userId, int $pathId)
    {
        parent::__construct(
            "User {$userId} is already enrolled in learning path {$pathId}",
            [
                'user_id' => $userId,
                'learning_path_id' => $pathId,
            ]
        );
    }
}
