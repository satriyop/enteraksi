<?php

namespace App\Domain\LearningPath\States;

class DroppedPathState extends PathEnrollmentState
{
    public static string $name = 'dropped';

    public function label(): string
    {
        return 'Keluar';
    }

    public function canAccessContent(): bool
    {
        return false;
    }

    public function canTrackProgress(): bool
    {
        return false;
    }

    public function canUnlockCourses(): bool
    {
        return false;
    }
}
