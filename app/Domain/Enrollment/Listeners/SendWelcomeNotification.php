<?php

namespace App\Domain\Enrollment\Listeners;

use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Notifications\WelcomeToCourseMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(UserEnrolled $event): void
    {
        $user = $event->enrollment->user;
        $course = $event->enrollment->course;

        $user->notify(new WelcomeToCourseMail($course));
    }
}
