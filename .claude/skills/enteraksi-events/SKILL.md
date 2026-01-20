---
name: enteraksi-events
description: Event-driven architecture patterns for Enteraksi LMS. Use when creating domain events, event listeners, or implementing audit logging.
triggers:
  - domain event
  - create event
  - dispatch event
  - event listener
  - event handler
  - audit log
  - event log
  - activity log
  - UserEnrolled
  - CoursePublished
  - EnrollmentCompleted
  - event driven
  - EventServiceProvider
---

# Enteraksi Event-Driven Architecture

## When to Use This Skill

- Creating new domain events
- Adding event listeners
- Implementing audit/activity logging
- Sending notifications on domain actions
- Understanding the event flow in the codebase

## Existing Domain Events

### Course Events
| Event | Trigger | Listeners |
|-------|---------|-----------|
| `CoursePublished` | Course status → Published | LogDomainEvent |
| `CourseUnpublished` | Course status → Draft | LogDomainEvent |
| `CourseArchived` | Course status → Archived | LogDomainEvent |

### Enrollment Events
| Event | Trigger | Listeners |
|-------|---------|-----------|
| `UserEnrolled` | New enrollment created | LogDomainEvent, SendWelcomeNotification |
| `UserReenrolled` | Dropped → Active | LogDomainEvent |
| `EnrollmentCompleted` | 100% progress | LogDomainEvent, SendCompletionCongratulations, UpdatePathProgress |
| `UserDropped` | User drops course | LogDomainEvent |

### Progress Events
| Event | Trigger | Listeners |
|-------|---------|-----------|
| `LessonCompleted` | Lesson marked complete | LogDomainEvent |
| `ProgressUpdated` | Progress percentage changes | (optional logging) |

### Learning Path Events
| Event | Trigger | Listeners |
|-------|---------|-----------|
| `PathEnrollmentCreated` | User enrolls in path | LogDomainEvent, SendPathEnrollmentWelcome |
| `PathCompleted` | All courses in path complete | LogDomainEvent, SendPathCompletionCongratulations |
| `PathDropped` | User drops from path | LogDomainEvent |
| `CourseUnlockedInPath` | Prerequisites met | LogDomainEvent |

## Key Patterns

### 1. DomainEvent Base Class

```php
// app/Domain/Shared/Contracts/DomainEvent.php
namespace App\Domain\Shared\Contracts;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class DomainEvent
{
    use Dispatchable, SerializesModels;

    public readonly DateTimeImmutable $occurredAt;
    public readonly ?int $actorId;
    public readonly string $eventId;

    public function __construct(?int $actorId = null)
    {
        $this->occurredAt = new DateTimeImmutable;
        $this->actorId = $actorId ?? auth()->id();
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Event name for logging (e.g., 'enrollment.created').
     */
    abstract public function getEventName(): string;

    /**
     * Metadata for audit logging.
     */
    abstract public function getMetadata(): array;

    /**
     * Primary entity ID affected.
     */
    abstract public function getAggregateId(): int|string;

    /**
     * Entity type (e.g., 'course', 'enrollment').
     */
    abstract public function getAggregateType(): string;

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

### 2. Concrete Domain Event

```php
// app/Domain/Enrollment/Events/UserEnrolled.php
namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Enrollment;

class UserEnrolled extends DomainEvent
{
    public function __construct(
        public readonly Enrollment $enrollment,
        ?int $actorId = null
    ) {
        parent::__construct($actorId);
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
}
```

### 3. LogDomainEvent Listener (Audit Log)

```php
// app/Domain/Shared/Listeners/LogDomainEvent.php
namespace App\Domain\Shared\Listeners;

use App\Domain\Shared\Contracts\DomainEvent;
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
        Log::channel('single')->info($event->getEventName(), $event->toArray());
    }
}
```

### 4. Notification Listener

```php
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

### 5. EventServiceProvider Registration

```php
// app/Providers/EventServiceProvider.php
namespace App\Providers;

use App\Domain\Enrollment\Events\UserEnrolled;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\Listeners\SendWelcomeNotification;
use App\Domain\Enrollment\Listeners\SendCompletionCongratulations;
use App\Domain\Shared\Listeners\LogDomainEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Enrollment Events
        UserEnrolled::class => [
            LogDomainEvent::class,          // Always log first
            SendWelcomeNotification::class, // Then send notifications
        ],
        EnrollmentCompleted::class => [
            LogDomainEvent::class,
            SendCompletionCongratulations::class,
            UpdatePathProgressOnCourseCompletion::class,
        ],

        // Course Events
        CoursePublished::class => [
            LogDomainEvent::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;  // Explicit registration preferred
    }
}
```

### 6. Dispatching Events in Services

```php
// In EnrollmentService
use Illuminate\Support\Facades\DB;

public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult
{
    return DB::transaction(function () use ($dto) {
        $enrollment = Enrollment::create([...]);

        // Dispatch inside transaction
        UserEnrolled::dispatch($enrollment);

        return new EnrollmentResult($enrollment, true);
    });
}
```

## Event Naming Convention

| Pattern | Example | Use For |
|---------|---------|---------|
| `{entity}.{action}` | `enrollment.created` | CRUD actions |
| `{entity}.{past_verb}` | `course.published` | State changes |
| `{entity}.{compound}` | `learning_path.course_unlocked` | Complex actions |

## Domain Event Log Schema

```sql
CREATE TABLE domain_event_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,          -- UUID
    event_name VARCHAR(100) NOT NULL,       -- e.g., 'enrollment.created'
    aggregate_type VARCHAR(50) NOT NULL,    -- e.g., 'enrollment'
    aggregate_id VARCHAR(50) NOT NULL,      -- Primary key of affected entity
    actor_id BIGINT UNSIGNED NULL,          -- User who triggered
    metadata JSON NOT NULL,                 -- Event-specific data
    occurred_at TIMESTAMP NOT NULL,         -- When event occurred
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_name (event_name),
    INDEX idx_aggregate (aggregate_type, aggregate_id),
    INDEX idx_actor (actor_id)
);
```

## Gotchas & Best Practices

1. **Always dispatch in DB::transaction()** - Ensures event only fires on success
2. **LogDomainEvent should be first listener** - Capture before anything else fails
3. **Use ShouldQueue for non-critical listeners** - Don't block the request
4. **Set specific queue names** - `'audit'`, `'notifications'`, `'default'`
5. **Serialize models with SerializesModels** - Already in base class
6. **Include enough metadata** - Think about what you need for debugging/reporting
7. **Event name should be past tense** - `created`, `completed`, `published`

## Testing Events

```php
// tests/Feature/EnrollmentTest.php
use Illuminate\Support\Facades\Event;

it('dispatches UserEnrolled event on enrollment', function () {
    Event::fake();

    $service = app(EnrollmentServiceContract::class);
    $result = $service->enroll(new CreateEnrollmentDTO(
        userId: $user->id,
        courseId: $course->id,
    ));

    Event::assertDispatched(UserEnrolled::class, function ($event) use ($result) {
        return $event->enrollment->id === $result->enrollment->id;
    });
});

// Or use custom assertion from TestCase
$this->assertEventLogged('enrollment.created', [
    'user_id' => $user->id,
    'course_id' => $course->id,
]);
```

## Quick Reference

```bash
# Files to reference
app/Domain/Shared/Contracts/DomainEvent.php         # Base event class
app/Domain/Enrollment/Events/UserEnrolled.php       # Event example
app/Domain/Shared/Listeners/LogDomainEvent.php      # Audit listener
app/Providers/EventServiceProvider.php              # Registration

# Create new event
php artisan make:class Domain/MyContext/Events/MyEvent

# Migration for event log
database/migrations/*_create_domain_event_log_table.php
```
