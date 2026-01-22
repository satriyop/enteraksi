<?php

namespace App\Domain\LearningPath\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class CourseNotInPathException extends DomainException
{
    public function __construct(int $courseId, int $learningPathId)
    {
        parent::__construct(
            "Course {$courseId} is not part of learning path {$learningPathId}",
            [
                'course_id' => $courseId,
                'learning_path_id' => $learningPathId,
            ]
        );
    }
}
