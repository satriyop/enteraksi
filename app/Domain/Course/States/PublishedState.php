<?php

namespace App\Domain\Course\States;

class PublishedState extends CourseState
{
    public static string $name = 'published';

    public function label(): string
    {
        return 'Dipublikasikan';
    }

    public function color(): string
    {
        return 'green';
    }

    public function canEdit(): bool
    {
        return false;
    }

    public function canEnroll(): bool
    {
        return true;
    }
}
