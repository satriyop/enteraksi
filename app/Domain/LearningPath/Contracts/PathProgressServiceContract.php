<?php

namespace App\Domain\LearningPath\Contracts;

use App\Domain\LearningPath\DTOs\PathProgressResult;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPathEnrollment;

interface PathProgressServiceContract
{
    /**
     * Get comprehensive progress for a path enrollment.
     */
    public function getProgress(LearningPathEnrollment $enrollment): PathProgressResult;

    /**
     * Calculate overall progress percentage for the path.
     */
    public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int;

    /**
     * Check if prerequisites are met for a course in the path.
     */
    public function checkPrerequisites(
        LearningPathEnrollment $enrollment,
        Course $course
    ): PrerequisiteCheckResult;

    /**
     * Check if a course is unlocked in the path.
     */
    public function isCourseUnlocked(LearningPathEnrollment $enrollment, Course $course): bool;

    /**
     * Unlock next available courses after a completion.
     *
     * @return Course[] Array of newly unlocked courses
     */
    public function unlockNextCourses(LearningPathEnrollment $enrollment): array;

    /**
     * Handle course enrollment completion within the path context.
     */
    public function onCourseCompleted(
        LearningPathEnrollment $pathEnrollment,
        Enrollment $courseEnrollment
    ): void;

    /**
     * Check if all required courses in the path are completed.
     */
    public function isPathCompleted(LearningPathEnrollment $enrollment): bool;
}
