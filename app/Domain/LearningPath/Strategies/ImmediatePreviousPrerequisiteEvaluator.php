<?php

namespace App\Domain\LearningPath\Strategies;

use App\Domain\LearningPath\Contracts\PrerequisiteEvaluatorContract;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;

/**
 * Evaluates prerequisites based only on the immediately previous course.
 * Only the course directly before needs to be completed.
 */
class ImmediatePreviousPrerequisiteEvaluator implements PrerequisiteEvaluatorContract
{
    public function evaluate(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        $path = $enrollment->learningPath;

        // Get the course position in the path
        /** @var Course|null $pathCourse */
        $pathCourse = $path->courses()
            ->where('course_id', $course->id)
            ->first();

        if (! $pathCourse) {
            return PrerequisiteCheckResult::notMet(
                [],
                'Course not found in path'
            );
        }

        /** @var object{position: int} $pivot */
        $pivot = $pathCourse->pivot;
        $position = $pivot->position;

        // First course is always available
        if ($position === 1) {
            return PrerequisiteCheckResult::met();
        }

        // Get the immediately previous course
        /** @var Course|null $previousCourse */
        $previousCourse = $path->courses()
            ->wherePivot('position', $position - 1)
            ->first();

        if (! $previousCourse) {
            // No previous course found, allow access
            return PrerequisiteCheckResult::met();
        }

        /** @var LearningPathCourseProgress|null $progress */
        $progress = $enrollment->courseProgress()
            ->where('course_id', $previousCourse->id)
            ->first();

        if ($progress && $progress->isCompleted()) {
            return PrerequisiteCheckResult::met();
        }

        return PrerequisiteCheckResult::notMet(
            [['id' => $previousCourse->id, 'title' => $previousCourse->title]],
            'Previous course must be completed'
        );
    }

    public function getName(): string
    {
        return 'immediate_previous';
    }
}
