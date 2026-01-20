<?php

namespace App\Domain\LearningPath\Strategies;

use App\Domain\LearningPath\Contracts\PrerequisiteEvaluatorContract;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\LearningPathEnrollment;

/**
 * Evaluates prerequisites based on sequential course ordering.
 * All previous courses in the path must be completed before unlocking the next.
 */
class SequentialPrerequisiteEvaluator implements PrerequisiteEvaluatorContract
{
    public function evaluate(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult {
        $path = $enrollment->learningPath;

        // Get the course position in the path
        $pathCourse = $path->courses()
            ->where('course_id', $course->id)
            ->first();

        if (! $pathCourse) {
            return PrerequisiteCheckResult::notMet(
                ['Course is not part of this learning path'],
                'Course not found in path'
            );
        }

        $position = $pathCourse->pivot->position;

        // First course is always available
        if ($position === 1) {
            return PrerequisiteCheckResult::met();
        }

        // Get all courses that should be completed before this one
        $previousCourses = $path->courses()
            ->wherePivot('position', '<', $position)
            ->orderBy('learning_path_course.position')
            ->get();

        $missingPrerequisites = [];

        foreach ($previousCourses as $previousCourse) {
            $progress = $enrollment->courseProgress()
                ->where('course_id', $previousCourse->id)
                ->first();

            if (! $progress || ! $progress->isCompleted()) {
                $missingPrerequisites[] = $previousCourse->title;
            }
        }

        if (empty($missingPrerequisites)) {
            return PrerequisiteCheckResult::met();
        }

        return PrerequisiteCheckResult::notMet(
            $missingPrerequisites,
            'Previous courses must be completed'
        );
    }

    public function getName(): string
    {
        return 'sequential';
    }
}
