<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class AlreadyEnrolledException extends DomainException
{
    public function __construct(int $userId, int $courseId)
    {
        parent::__construct(
            "User {$userId} is already enrolled in course {$courseId}",
            [
                'user_id' => $userId,
                'course_id' => $courseId,
            ]
        );
    }
}
