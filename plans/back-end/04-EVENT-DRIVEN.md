# Phase 4: Event-Driven Architecture

**Duration**: Week 8-9
**Dependencies**: Phase 3 complete
**Priority**: High - Enables decoupling and extensibility

---

## Objectives

1. Create domain events for all significant business actions
2. Implement event listeners for side effects
3. Build notification system foundation
4. Enable audit logging through events
5. Prepare infrastructure for future integrations

---

## 4.1 Why Events?

### Current Problems

```php
// Current: Tight coupling, side effects inline
public function markCompleted(): self
{
    $this->is_completed = true;
    $this->completed_at = now();
    $this->save();

    // Side effect: Recalculate course progress
    $this->enrollment->recalculateCourseProgress();

    // Missing: Notifications
    // Missing: Audit logging
    // Missing: Analytics tracking
    // Missing: External integrations

    return $this;
}
```

### With Events

```php
// After: Clean separation, extensible
public function markCompleted(): self
{
    $this->is_completed = true;
    $this->completed_at = now();
    $this->save();

    // Single event, multiple listeners handle side effects
    LessonCompleted::dispatch($this->enrollment, $this->lesson);

    return $this;
}

// Listeners (separate concerns):
// - RecalculateCourseProgress
// - SendProgressNotification
// - LogLessonCompletion
// - UpdateAnalytics
// - TriggerWebhook
```

---

## 4.2 Event Categories

### Domain Events (Business Logic)

| Event | Trigger | Purpose |
|-------|---------|---------|
| `CoursePublished` | Course goes live | Notify enrolled users |
| `CourseUnpublished` | Course taken offline | Warn active learners |
| `CourseArchived` | Course retired | Historical tracking |
| `UserEnrolled` | New enrollment | Welcome email, progress init |
| `EnrollmentCompleted` | All lessons done | Certificate, celebration |
| `UserDropped` | User leaves course | Analytics, cleanup |
| `LessonCompleted` | Lesson finished | Progress update |
| `ProgressUpdated` | Any progress change | Dashboard updates |
| `AttemptStarted` | Assessment begun | Time tracking |
| `AssessmentSubmitted` | Answers submitted | Grading queue |
| `AssessmentGraded` | Grading complete | Results notification |
| `AnswerGraded` | Manual grading | Partial results |

### System Events (Infrastructure)

| Event | Trigger | Purpose |
|-------|---------|---------|
| `StateTransitioned` | Any state change | Audit logging |
| `ActionFailed` | Service failure | Error tracking |

---

## 4.3 Domain Event Base Class

```php
<?php
// app/Domain/Shared/Events/DomainEvent.php

namespace App\Domain\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class DomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly \DateTimeImmutable $occurredAt;
    public readonly ?int $actorId;
    public readonly string $eventId;

    public function __construct(?int $actorId = null)
    {
        $this->occurredAt = new \DateTimeImmutable();
        $this->actorId = $actorId ?? auth()->id();
        $this->eventId = (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Get the event name for logging/debugging.
     */
    abstract public function getEventName(): string;

    /**
     * Get event metadata for audit logging.
     *
     * @return array<string, mixed>
     */
    abstract public function getMetadata(): array;

    /**
     * Get the primary entity ID affected by this event.
     */
    abstract public function getAggregateId(): int|string;

    /**
     * Get the aggregate type (e.g., 'course', 'enrollment').
     */
    abstract public function getAggregateType(): string;

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->getEventName(),
            'aggregate_type' => $this->getAggregateType(),
            'aggregate_id' => $this->getAggregateId(),
            'actor_id' => $this->actorId,
            'occurred_at' => $this->occurredAt->format('c'),
            'metadata' => $this->getMetadata(),
        ];
    }
}
```

---

## 4.4 Course Events

```php
<?php
// app/Domain/Course/Events/CoursePublished.php

namespace App\Domain\Course\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Course;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CoursePublished extends DomainEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Course $course,
        public readonly ?int $publisherId = null,
        public readonly ?string $previousStatus = null
    ) {
        parent::__construct($publisherId);
    }

    public function getEventName(): string
    {
        return 'course.published';
    }

    public function getMetadata(): array
    {
        return [
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'publisher_id' => $this->publisherId,
            'previous_status' => $this->previousStatus,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->course->id;
    }

    public function getAggregateType(): string
    {
        return 'course';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('courses'),
            new Channel("course.{$this->course->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'course.published';
    }
}
```

```php
<?php
// app/Domain/Course/Events/CourseUnpublished.php

namespace App\Domain\Course\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Course;

class CourseUnpublished extends DomainEvent
{
    public function __construct(
        public readonly Course $course,
        public readonly string $previousStatus
    ) {
        parent::__construct();
    }

    public function getEventName(): string
    {
        return 'course.unpublished';
    }

    public function getMetadata(): array
    {
        return [
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'previous_status' => $this->previousStatus,
            'active_enrollments_count' => $this->course->enrollments()->active()->count(),
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->course->id;
    }

    public function getAggregateType(): string
    {
        return 'course';
    }
}
```

```php
<?php
// app/Domain/Course/Events/CourseArchived.php

namespace App\Domain\Course\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Course;

class CourseArchived extends DomainEvent
{
    public function __construct(
        public readonly Course $course,
        public readonly string $previousStatus
    ) {
        parent::__construct();
    }

    public function getEventName(): string
    {
        return 'course.archived';
    }

    public function getMetadata(): array
    {
        return [
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'previous_status' => $this->previousStatus,
            'total_enrollments' => $this->course->enrollments()->count(),
            'completed_enrollments' => $this->course->enrollments()->completed()->count(),
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->course->id;
    }

    public function getAggregateType(): string
    {
        return 'course';
    }
}
```

---

## 4.5 Enrollment Events

```php
<?php
// app/Domain/Enrollment/Events/UserEnrolled.php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Enrollment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserEnrolled extends DomainEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Enrollment $enrollment
    ) {
        parent::__construct($enrollment->user_id);
    }

    public function getEventName(): string
    {
        return 'enrollment.created';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'invited_by' => $this->enrollment->invited_by,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'enrollment';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->enrollment->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'enrollment.created';
    }
}
```

```php
<?php
// app/Domain/Enrollment/Events/EnrollmentCompleted.php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Enrollment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class EnrollmentCompleted extends DomainEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Enrollment $enrollment
    ) {
        parent::__construct($enrollment->user_id);
    }

    public function getEventName(): string
    {
        return 'enrollment.completed';
    }

    public function getMetadata(): array
    {
        $enrollment = $this->enrollment;

        return [
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'user_name' => $enrollment->user->name,
            'course_id' => $enrollment->course_id,
            'course_title' => $enrollment->course->title,
            'enrolled_at' => $enrollment->enrolled_at?->toISOString(),
            'completed_at' => $enrollment->completed_at?->toISOString(),
            'total_time_spent' => $enrollment->lessonProgress()->sum('time_spent_seconds'),
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'enrollment';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->enrollment->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'enrollment.completed';
    }
}
```

```php
<?php
// app/Domain/Enrollment/Events/UserDropped.php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Enrollment;

class UserDropped extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly ?string $reason = null
    ) {
        parent::__construct($enrollment->user_id);
    }

    public function getEventName(): string
    {
        return 'enrollment.dropped';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'course_id' => $this->enrollment->course_id,
            'reason' => $this->reason,
            'progress_at_drop' => $this->enrollment->progress_percentage,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'enrollment';
    }
}
```

---

## 4.6 Progress Events

```php
<?php
// app/Domain/Progress/Events/LessonCompleted.php

namespace App\Domain\Progress\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LessonCompleted extends DomainEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson
    ) {
        parent::__construct($enrollment->user_id);
    }

    public function getEventName(): string
    {
        return 'lesson.completed';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'lesson_id' => $this->lesson->id,
            'lesson_title' => $this->lesson->title,
            'course_id' => $this->enrollment->course_id,
            'course_title' => $this->enrollment->course->title,
            'course_progress' => $this->enrollment->progress_percentage,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'enrollment';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->enrollment->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lesson.completed';
    }
}
```

```php
<?php
// app/Domain/Progress/Events/ProgressUpdated.php

namespace App\Domain\Progress\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ProgressUpdated extends DomainEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly LessonProgress $progress
    ) {
        parent::__construct($enrollment->user_id);
    }

    public function getEventName(): string
    {
        return 'progress.updated';
    }

    public function getMetadata(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => $this->enrollment->user_id,
            'lesson_id' => $this->progress->lesson_id,
            'course_progress' => $this->enrollment->progress_percentage,
            'lesson_progress' => $this->progress->progress_percentage ?? 0,
            'is_completed' => $this->progress->is_completed,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->enrollment->id;
    }

    public function getAggregateType(): string
    {
        return 'enrollment';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->enrollment->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'progress.updated';
    }
}
```

---

## 4.7 Assessment Events

```php
<?php
// app/Domain/Assessment/Events/AttemptStarted.php

namespace App\Domain\Assessment\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\AssessmentAttempt;

class AttemptStarted extends DomainEvent
{
    public function __construct(
        public readonly AssessmentAttempt $attempt
    ) {
        parent::__construct($attempt->user_id);
    }

    public function getEventName(): string
    {
        return 'assessment.attempt.started';
    }

    public function getMetadata(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'assessment_id' => $this->attempt->assessment_id,
            'assessment_title' => $this->attempt->assessment->title,
            'user_id' => $this->attempt->user_id,
            'attempt_number' => $this->attempt->attempt_number,
            'started_at' => $this->attempt->started_at->toISOString(),
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->attempt->id;
    }

    public function getAggregateType(): string
    {
        return 'assessment_attempt';
    }
}
```

```php
<?php
// app/Domain/Assessment/Events/AssessmentSubmitted.php

namespace App\Domain\Assessment\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\AssessmentAttempt;

class AssessmentSubmitted extends DomainEvent
{
    public function __construct(
        public readonly AssessmentAttempt $attempt
    ) {
        parent::__construct($attempt->user_id);
    }

    public function getEventName(): string
    {
        return 'assessment.submitted';
    }

    public function getMetadata(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'assessment_id' => $this->attempt->assessment_id,
            'user_id' => $this->attempt->user_id,
            'status' => $this->attempt->status,
            'requires_manual_grading' => $this->attempt->status === 'submitted',
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->attempt->id;
    }

    public function getAggregateType(): string
    {
        return 'assessment_attempt';
    }
}
```

```php
<?php
// app/Domain/Assessment/Events/AssessmentGraded.php

namespace App\Domain\Assessment\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Models\AssessmentAttempt;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AssessmentGraded extends DomainEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly AssessmentAttempt $attempt
    ) {
        parent::__construct($attempt->graded_by ?? $attempt->user_id);
    }

    public function getEventName(): string
    {
        return 'assessment.graded';
    }

    public function getMetadata(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'assessment_id' => $this->attempt->assessment_id,
            'assessment_title' => $this->attempt->assessment->title,
            'user_id' => $this->attempt->user_id,
            'score' => $this->attempt->score,
            'max_score' => $this->attempt->max_score,
            'percentage' => $this->attempt->percentage,
            'passed' => $this->attempt->passed,
            'graded_by' => $this->attempt->graded_by,
        ];
    }

    public function getAggregateId(): int|string
    {
        return $this->attempt->id;
    }

    public function getAggregateType(): string
    {
        return 'assessment_attempt';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->attempt->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'assessment.graded';
    }
}
```

---

## 4.8 Event Listeners

### Audit Log Listener

```php
<?php
// app/Domain/Shared/Listeners/LogDomainEvent.php

namespace App\Domain\Shared\Listeners;

use App\Domain\Shared\Events\DomainEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogDomainEvent implements ShouldQueue
{
    public string $queue = 'audit';

    public function handle(DomainEvent $event): void
    {
        // Log to database
        DB::table('domain_event_log')->insert([
            'event_id' => $event->eventId,
            'event_name' => $event->getEventName(),
            'aggregate_type' => $event->getAggregateType(),
            'aggregate_id' => $event->getAggregateId(),
            'actor_id' => $event->actorId,
            'metadata' => json_encode($event->getMetadata()),
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s'),
            'created_at' => now(),
        ]);

        // Also log to file for debugging
        Log::channel('events')->info($event->getEventName(), $event->toArray());
    }
}
```

### Notification Listeners

```php
<?php
// app/Domain/Enrollment/Listeners/SendWelcomeNotification.php

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
```

```php
<?php
// app/Domain/Enrollment/Listeners/SendCompletionCongratulations.php

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
```

```php
<?php
// app/Domain/Assessment/Listeners/NotifyUserOfGradedAssessment.php

namespace App\Domain\Assessment\Listeners;

use App\Domain\Assessment\Events\AssessmentGraded;
use App\Domain\Assessment\Notifications\AssessmentGradedMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUserOfGradedAssessment implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(AssessmentGraded $event): void
    {
        $user = $event->attempt->user;

        $user->notify(new AssessmentGradedMail($event->attempt));
    }
}
```

### Progress Calculation Listener

```php
<?php
// app/Domain/Progress/Listeners/RecalculateCourseProgress.php

namespace App\Domain\Progress\Listeners;

use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\Events\LessonCompleted;

class RecalculateCourseProgress
{
    public function __construct(
        protected ProgressTrackingServiceContract $progressService
    ) {}

    public function handle(LessonCompleted $event): void
    {
        // Recalculate course progress
        $this->progressService->recalculateCourseProgress($event->enrollment);
    }
}
```

### Certificate Generation Listener

```php
<?php
// app/Domain/Enrollment/Listeners/GenerateCertificate.php

namespace App\Domain\Enrollment\Listeners;

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Certificate\Services\CertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateCertificate implements ShouldQueue
{
    public string $queue = 'certificates';

    public function __construct(
        protected CertificateService $certificateService
    ) {}

    public function handle(EnrollmentCompleted $event): void
    {
        $this->certificateService->generateForEnrollment($event->enrollment);
    }
}
```

---

## 4.9 Event Service Provider

```php
<?php
// app/Providers/EventServiceProvider.php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// Course Events
use App\Domain\Course\Events\CoursePublished;
use App\Domain\Course\Events\CourseUnpublished;
use App\Domain\Course\Events\CourseArchived;

// Enrollment Events
use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Events\UserDropped;

// Progress Events
use App\Domain\Progress\Events\LessonCompleted;
use App\Domain\Progress\Events\ProgressUpdated;

// Assessment Events
use App\Domain\Assessment\Events\AttemptStarted;
use App\Domain\Assessment\Events\AssessmentSubmitted;
use App\Domain\Assessment\Events\AssessmentGraded;

// Listeners
use App\Domain\Shared\Listeners\LogDomainEvent;
use App\Domain\Enrollment\Listeners\SendWelcomeNotification;
use App\Domain\Enrollment\Listeners\SendCompletionCongratulations;
use App\Domain\Enrollment\Listeners\GenerateCertificate;
use App\Domain\Progress\Listeners\RecalculateCourseProgress;
use App\Domain\Assessment\Listeners\NotifyUserOfGradedAssessment;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen = [
        // Course Events
        CoursePublished::class => [
            LogDomainEvent::class,
            // NotifyEnrolledUsersOfUpdate::class, // Future
        ],
        CourseUnpublished::class => [
            LogDomainEvent::class,
            // WarnActiveLearnersOfUnpublish::class, // Future
        ],
        CourseArchived::class => [
            LogDomainEvent::class,
        ],

        // Enrollment Events
        UserEnrolled::class => [
            LogDomainEvent::class,
            SendWelcomeNotification::class,
        ],
        EnrollmentCompleted::class => [
            LogDomainEvent::class,
            SendCompletionCongratulations::class,
            GenerateCertificate::class,
        ],
        UserDropped::class => [
            LogDomainEvent::class,
        ],

        // Progress Events
        LessonCompleted::class => [
            LogDomainEvent::class,
            RecalculateCourseProgress::class,
        ],
        ProgressUpdated::class => [
            // LogDomainEvent::class, // Too noisy, optional
        ],

        // Assessment Events
        AttemptStarted::class => [
            LogDomainEvent::class,
        ],
        AssessmentSubmitted::class => [
            LogDomainEvent::class,
            // QueueForGrading::class, // Future
        ],
        AssessmentGraded::class => [
            LogDomainEvent::class,
            NotifyUserOfGradedAssessment::class,
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
        return false; // Explicit registration for clarity
    }
}
```

---

## 4.10 Event Log Migration

```php
<?php
// database/migrations/xxxx_create_domain_event_log_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_event_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_name', 100)->index();
            $table->string('aggregate_type', 50)->index();
            $table->unsignedBigInteger('aggregate_id')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata');
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at');

            // Composite index for common queries
            $table->index(['aggregate_type', 'aggregate_id', 'occurred_at']);
            $table->index(['event_name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_event_log');
    }
};
```

---

## 4.11 Notifications

### Welcome Email Notification

```php
<?php
// app/Domain/Enrollment/Notifications/WelcomeToCourseMail.php

namespace App\Domain\Enrollment\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeToCourseMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Selamat bergabung di {$this->course->title}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Anda telah berhasil mendaftar di kursus \"{$this->course->title}\".")
            ->line("Mulai perjalanan belajar Anda sekarang!")
            ->action('Mulai Belajar', route('courses.learn', $this->course))
            ->line('Semoga sukses dalam pembelajaran Anda!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'enrollment.welcome',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'message' => "Anda berhasil mendaftar di kursus \"{$this->course->title}\"",
        ];
    }
}
```

### Course Completed Notification

```php
<?php
// app/Domain/Enrollment/Notifications/CourseCompletedMail.php

namespace App\Domain\Enrollment\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseCompletedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course,
        public readonly Enrollment $enrollment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Selamat! Anda telah menyelesaikan {$this->course->title}")
            ->greeting("Selamat, {$notifiable->name}!")
            ->line("Anda telah berhasil menyelesaikan kursus \"{$this->course->title}\".")
            ->line("Sertifikat Anda akan segera tersedia.")
            ->action('Lihat Sertifikat', route('certificates.show', $this->enrollment))
            ->line('Terima kasih telah belajar bersama kami!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'enrollment.completed',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'enrollment_id' => $this->enrollment->id,
            'message' => "Selamat! Anda menyelesaikan kursus \"{$this->course->title}\"",
        ];
    }
}
```

---

## 4.12 Testing Events

### Event Dispatch Tests

```php
<?php
// tests/Unit/Domain/Enrollment/Events/UserEnrolledTest.php

use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Listeners\SendWelcomeNotification;
use App\Domain\Enrollment\Notifications\WelcomeToCourseMail;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

describe('UserEnrolled Event', function () {
    it('dispatches when user enrolls', function () {
        Event::fake([UserEnrolled::class]);

        $user = User::factory()->create();
        $course = Course::factory()->published()->create();

        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        UserEnrolled::dispatch($enrollment);

        Event::assertDispatched(UserEnrolled::class, function ($event) use ($enrollment) {
            return $event->enrollment->id === $enrollment->id;
        });
    });

    it('sends welcome notification', function () {
        Notification::fake();

        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        $event = new UserEnrolled($enrollment);
        $listener = new SendWelcomeNotification();
        $listener->handle($event);

        Notification::assertSentTo($user, WelcomeToCourseMail::class);
    });

    it('contains correct metadata', function () {
        $enrollment = Enrollment::factory()->create();

        $event = new UserEnrolled($enrollment);
        $metadata = $event->getMetadata();

        expect($metadata)->toHaveKeys([
            'enrollment_id',
            'user_id',
            'course_id',
            'course_title',
            'invited_by',
        ]);
        expect($metadata['enrollment_id'])->toBe($enrollment->id);
    });
});
```

### Listener Tests

```php
<?php
// tests/Unit/Domain/Progress/Listeners/RecalculateCourseProgressTest.php

use App\Domain\Progress\Events\LessonCompleted;
use App\Domain\Progress\Listeners\RecalculateCourseProgress;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

describe('RecalculateCourseProgress Listener', function () {
    it('calls progress service to recalculate', function () {
        $enrollment = Enrollment::factory()->create();
        $lesson = Lesson::factory()->create();

        $progressService = Mockery::mock(ProgressTrackingServiceContract::class);
        $progressService->shouldReceive('recalculateCourseProgress')
            ->once()
            ->with($enrollment)
            ->andReturn(50.0);

        $listener = new RecalculateCourseProgress($progressService);
        $event = new LessonCompleted($enrollment, $lesson);

        $listener->handle($event);
    });
});
```

---

## 4.13 Implementation Checklist

### Week 8: Core Events

- [ ] Event Infrastructure
  - [ ] Create DomainEvent base class
  - [ ] Create domain_event_log migration
  - [ ] Create LogDomainEvent listener
  - [ ] Configure event logging channel

- [ ] Course Events
  - [ ] CoursePublished
  - [ ] CourseUnpublished
  - [ ] CourseArchived

- [ ] Enrollment Events
  - [ ] UserEnrolled
  - [ ] EnrollmentCompleted
  - [ ] UserDropped

### Week 9: Assessment Events & Notifications

- [ ] Progress Events
  - [ ] LessonCompleted
  - [ ] ProgressUpdated

- [ ] Assessment Events
  - [ ] AttemptStarted
  - [ ] AssessmentSubmitted
  - [ ] AssessmentGraded

- [ ] Notification System
  - [ ] WelcomeToCourseMail
  - [ ] CourseCompletedMail
  - [ ] AssessmentGradedMail

- [ ] Event Service Provider
  - [ ] Register all event-listener mappings
  - [ ] Test event dispatching
  - [ ] Test listener execution

---

## Next Phase

Once Phase 4 is complete, proceed to [Phase 5: Dependency Injection & Strategy Patterns](./05-DI-STRATEGY.md).

Events are now flowing, and Phase 5 will make the system flexible by introducing strategy patterns for grading, notifications, and progress calculation.
