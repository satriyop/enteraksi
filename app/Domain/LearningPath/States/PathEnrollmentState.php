<?php

namespace App\Domain\LearningPath\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PathEnrollmentState extends State
{
    /**
     * Get the human-readable label for this state in Indonesian.
     */
    abstract public function label(): string;

    /**
     * Whether the learner can access path content in this state.
     */
    abstract public function canAccessContent(): bool;

    /**
     * Whether progress can be tracked in this state.
     */
    abstract public function canTrackProgress(): bool;

    /**
     * Whether new courses can be unlocked in this state.
     */
    abstract public function canUnlockCourses(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActivePathState::class)
            // Active -> Completed (all required courses done)
            ->allowTransition(ActivePathState::class, CompletedPathState::class)
            // Active -> Dropped (user drops out)
            ->allowTransition(ActivePathState::class, DroppedPathState::class)
            // Allow reactivation from dropped
            ->allowTransition(DroppedPathState::class, ActivePathState::class);
    }
}
