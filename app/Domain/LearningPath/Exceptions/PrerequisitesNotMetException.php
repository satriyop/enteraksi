<?php

namespace App\Domain\LearningPath\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class PrerequisitesNotMetException extends DomainException
{
    /**
     * @param  array<int, array{id: int, title: string}>  $missingPrerequisites
     */
    public function __construct(
        int $pathEnrollmentId,
        int $courseId,
        array $missingPrerequisites = []
    ) {
        $prereqIds = array_column($missingPrerequisites, 'id');
        $prereqList = implode(', ', $prereqIds);

        parent::__construct(
            "Prerequisites not met for course {$courseId} in path enrollment {$pathEnrollmentId}. Unmet: [{$prereqList}]",
            [
                'path_enrollment_id' => $pathEnrollmentId,
                'course_id' => $courseId,
                'missing_prerequisites' => $missingPrerequisites,
            ]
        );
    }
}
