<?php

namespace App\Domain\Enrollment\States;

class CompletedState extends EnrollmentState
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Selesai';
    }

    public function canAccessContent(): bool
    {
        return true; // Can still review content
    }

    public function canTrackProgress(): bool
    {
        return false; // No more progress to track
    }
}
