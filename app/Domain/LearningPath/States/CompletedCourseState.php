<?php

namespace App\Domain\LearningPath\States;

class CompletedCourseState extends CourseProgressState
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Selesai';
    }

    public function canStart(): bool
    {
        return true; // Can review
    }

    public function blocksNext(): bool
    {
        return false; // Does not block
    }
}
