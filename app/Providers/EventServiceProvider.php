<?php

namespace App\Providers;

use App\Domain\Course\Events\CourseArchived;
use App\Domain\Course\Events\CoursePublished;
use App\Domain\Course\Events\CourseUnpublished;
use App\Domain\Enrollment\Events\CourseStarted;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Events\UserDropped;
use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Listeners\SendCompletionCongratulations;
use App\Domain\Enrollment\Listeners\SendWelcomeNotification;
use App\Domain\LearningPath\Events\CourseUnlockedInPath;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Events\PathProgressUpdated;
use App\Domain\LearningPath\Listeners\SendPathCompletionCongratulations;
use App\Domain\LearningPath\Listeners\SendPathEnrollmentWelcome;
use App\Domain\LearningPath\Listeners\UpdatePathProgressOnCourseCompletion;
use App\Domain\LearningPath\Listeners\UpdatePathProgressOnCourseDrop;
use App\Domain\Progress\Events\LessonCompleted;
use App\Domain\Progress\Events\LessonDeleted;
use App\Domain\Progress\Events\ProgressUpdated;
use App\Domain\Progress\Listeners\RecalculateProgressOnLessonDeletion;
use App\Domain\Shared\Listeners\LogDomainEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Course Events
        CoursePublished::class => [
            LogDomainEvent::class,
        ],
        CourseUnpublished::class => [
            LogDomainEvent::class,
        ],
        CourseArchived::class => [
            LogDomainEvent::class,
        ],

        // Enrollment Events
        UserEnrolled::class => [
            LogDomainEvent::class,
            SendWelcomeNotification::class,
        ],
        CourseStarted::class => [
            LogDomainEvent::class,
        ],
        EnrollmentCompleted::class => [
            LogDomainEvent::class,
            SendCompletionCongratulations::class,
            UpdatePathProgressOnCourseCompletion::class,
        ],
        UserDropped::class => [
            LogDomainEvent::class,
            UpdatePathProgressOnCourseDrop::class,
        ],

        // Progress Events
        LessonCompleted::class => [
            LogDomainEvent::class,
        ],
        LessonDeleted::class => [
            LogDomainEvent::class,
            RecalculateProgressOnLessonDeletion::class,
        ],
        ProgressUpdated::class => [
            // LogDomainEvent::class, // Too noisy - uncomment if needed
        ],

        // Learning Path Events
        PathEnrollmentCreated::class => [
            LogDomainEvent::class,
            SendPathEnrollmentWelcome::class,
        ],
        PathCompleted::class => [
            LogDomainEvent::class,
            SendPathCompletionCongratulations::class,
        ],
        PathDropped::class => [
            LogDomainEvent::class,
        ],
        CourseUnlockedInPath::class => [
            LogDomainEvent::class,
        ],
        PathProgressUpdated::class => [
            // LogDomainEvent::class, // Too noisy - uncomment if needed
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
