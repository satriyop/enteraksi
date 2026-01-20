<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class CourseNotPublishedException extends DomainException
{
    public function __construct(int $courseId)
    {
        parent::__construct(
            "Course {$courseId} is not published and cannot accept enrollments",
            ['course_id' => $courseId]
        );
    }
}
