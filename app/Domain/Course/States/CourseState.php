<?php

namespace App\Domain\Course\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class CourseState extends State
{
    /**
     * Get the human-readable label for this state.
     */
    abstract public function label(): string;

    /**
     * Get the color associated with this state (for UI).
     */
    abstract public function color(): string;

    /**
     * Whether the course can be edited in this state.
     */
    abstract public function canEdit(): bool;

    /**
     * Whether the course can accept new enrollments in this state.
     */
    abstract public function canEnroll(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            // Draft -> Published
            ->allowTransition(DraftState::class, PublishedState::class)
            // Published -> Draft (unpublish)
            ->allowTransition(PublishedState::class, DraftState::class)
            // Published -> Archived
            ->allowTransition(PublishedState::class, ArchivedState::class)
            // Draft -> Archived
            ->allowTransition(DraftState::class, ArchivedState::class)
            // Archived -> Published (reactivate)
            ->allowTransition(ArchivedState::class, PublishedState::class)
            // Archived -> Draft
            ->allowTransition(ArchivedState::class, DraftState::class);
    }
}
