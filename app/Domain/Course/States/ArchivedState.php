<?php

namespace App\Domain\Course\States;

class ArchivedState extends CourseState
{
    public static string $name = 'archived';

    public function label(): string
    {
        return 'Diarsipkan';
    }

    public function color(): string
    {
        return 'yellow';
    }

    public function canEdit(): bool
    {
        return true; // Archived courses can be edited (e.g., to prepare for republishing)
    }

    public function canEnroll(): bool
    {
        return false;
    }
}
