<?php

namespace App\Domain\LearningPath\States;

class LockedCourseState extends CourseProgressState
{
    public static string $name = 'locked';

    public function label(): string
    {
        return 'Terkunci';
    }

    public function canStart(): bool
    {
        return false;
    }

    public function blocksNext(): bool
    {
        return true;
    }
}
