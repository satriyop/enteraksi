<?php

namespace App\Domain\Enrollment\States;

class DroppedState extends EnrollmentState
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
}
