<?php

namespace App\Domain\LearningPath\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class CourseProgressState extends State
{
    /**
     * Get the human-readable label in Indonesian.
     */
    abstract public function label(): string;

    /**
     * Whether the learner can start this course.
     */
    abstract public function canStart(): bool;

    /**
     * Whether this course blocks the next course.
     */
    abstract public function blocksNext(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(LockedCourseState::class)
            // Locked -> Available (prerequisites met)
            ->allowTransition(LockedCourseState::class, AvailableCourseState::class)
            // Available -> InProgress (user starts course)
            ->allowTransition(AvailableCourseState::class, InProgressCourseState::class)
            // InProgress -> Completed (user finishes course)
            ->allowTransition(InProgressCourseState::class, CompletedCourseState::class)
            // Allow direct completion from available (e.g., already completed course enrollment)
            ->allowTransition(AvailableCourseState::class, CompletedCourseState::class);
    }
}
