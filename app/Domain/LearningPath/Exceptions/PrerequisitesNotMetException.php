<?php

namespace App\Domain\LearningPath\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class PrerequisitesNotMetException extends DomainException
{
    public function __construct(int $courseId, int $pathEnrollmentId, array $missingPrerequisites = [])
    {
        parent::__construct(
            "Prerequisites not met for course {$courseId} in path enrollment {$pathEnrollmentId}",
            [
                'course_id' => $courseId,
                'path_enrollment_id' => $pathEnrollmentId,
                'missing_prerequisites' => $missingPrerequisites,
            ]
        );
    }
}
