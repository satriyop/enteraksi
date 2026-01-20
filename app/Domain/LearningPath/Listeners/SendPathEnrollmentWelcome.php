<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Notifications\PathEnrollmentWelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPathEnrollmentWelcome implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PathEnrollmentCreated $event): void
    {
        $user = $event->enrollment->user;
        $path = $event->enrollment->learningPath;

        $user->notify(new PathEnrollmentWelcomeMail($path, $event->enrollment));
    }
}
