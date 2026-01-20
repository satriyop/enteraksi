<?php

namespace App\Domain\LearningPath\States;

class AvailableCourseState extends CourseProgressState
{
    public static string $name = 'available';

    public function label(): string
    {
        return 'Tersedia';
    }

    public function canStart(): bool
    {
        return true;
    }

    public function blocksNext(): bool
    {
        return true;
    }
}
