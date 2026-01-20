<?php

namespace App\Domain\Enrollment\States;

class ActiveState extends EnrollmentState
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
}
