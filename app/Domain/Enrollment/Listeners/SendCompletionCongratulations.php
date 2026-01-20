<?php

namespace App\Domain\Enrollment\Listeners;

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Notifications\CourseCompletedMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCompletionCongratulations implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(EnrollmentCompleted $event): void
    {
        $user = $event->enrollment->user;
        $course = $event->enrollment->course;

        $user->notify(new CourseCompletedMail($course, $event->enrollment));
    }
}
