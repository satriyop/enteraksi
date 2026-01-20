<?php

namespace App\Domain\LearningPath\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class CourseNotInPathException extends DomainException
{
    public function __construct(int $courseId, int $pathId)
    {
        parent::__construct(
            "Course {$courseId} is not part of learning path {$pathId}",
            [
                'course_id' => $courseId,
                'learning_path_id' => $pathId,
            ]
        );
    }
}
