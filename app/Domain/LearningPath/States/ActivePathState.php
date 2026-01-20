<?php

namespace App\Domain\LearningPath\States;

class ActivePathState extends PathEnrollmentState
{
    public static string $name = 'active';

    public function label(): string
    {
        return 'Aktif';
    }

    public function canAccessContent(): bool
    {
        return true;
    }

    public function canTrackProgress(): bool
    {
        return true;
    }

    public function canUnlockCourses(): bool
    {
        return true;
    }
}
