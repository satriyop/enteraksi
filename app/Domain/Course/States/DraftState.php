<?php

namespace App\Domain\Course\States;

class DraftState extends CourseState
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draf';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function canEdit(): bool
    {
        return true;
    }

    public function canEnroll(): bool
    {
        return false;
    }
}
