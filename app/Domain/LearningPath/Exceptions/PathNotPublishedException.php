<?php

namespace App\Domain\LearningPath\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class PathNotPublishedException extends DomainException
{
    public function __construct(int $pathId)
    {
        parent::__construct(
            "Learning path {$pathId} is not published and cannot accept enrollments",
            [
                'learning_path_id' => $pathId,
            ]
        );
    }
}
