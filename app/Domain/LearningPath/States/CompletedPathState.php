<?php

namespace App\Domain\LearningPath\States;

class CompletedPathState extends PathEnrollmentState
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Selesai';
    }

    public function canAccessContent(): bool
    {
        return true; // Can still access for review
    }

    public function canTrackProgress(): bool
    {
        return false; // No more progress to track
    }

    public function canUnlockCourses(): bool
    {
        return false; // All courses should be unlocked
    }
}
