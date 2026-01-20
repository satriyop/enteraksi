<?php

namespace App\Domain\LearningPath\States;

class InProgressCourseState extends CourseProgressState
{
    public static string $name = 'in_progress';

    public function label(): string
    {
        return 'Sedang Berlangsung';
    }

    public function canStart(): bool
    {
        return true; // Can continue
    }

    public function blocksNext(): bool
    {
        return true;
    }
}
