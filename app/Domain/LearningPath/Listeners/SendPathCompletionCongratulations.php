<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Notifications\PathCompletedMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPathCompletionCongratulations implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PathCompleted $event): void
    {
        $user = $event->enrollment->user;
        $path = $event->enrollment->learningPath;

        $user->notify(new PathCompletedMail($path, $event->enrollment, $event->completedCourses));
    }
}
