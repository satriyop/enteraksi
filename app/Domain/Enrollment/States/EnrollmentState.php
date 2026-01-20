<?php

namespace App\Domain\Enrollment\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EnrollmentState extends State
{
    /**
     * Get the human-readable label for this state.
     */
    abstract public function label(): string;

    /**
     * Whether the learner can access course content in this state.
     */
    abstract public function canAccessContent(): bool;

    /**
     * Whether progress can be tracked in this state.
     */
    abstract public function canTrackProgress(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActiveState::class)
            // Active -> Completed (finished all lessons)
            ->allowTransition(ActiveState::class, CompletedState::class)
            // Active -> Dropped (user drops out)
            ->allowTransition(ActiveState::class, DroppedState::class)
            // Allow reactivation from dropped
            ->allowTransition(DroppedState::class, ActiveState::class);
    }
}
