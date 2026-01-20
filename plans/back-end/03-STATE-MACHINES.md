# Phase 3: State Machine Implementation

**Duration**: Week 6-7
**Dependencies**: Phase 2 complete
**Priority**: High - Critical for data integrity and business rules

---

## Objectives

1. Implement formal state machines for all stateful entities
2. Add transition guards and validators
3. Create state transition audit logging
4. Enable state-based querying
5. Integrate with services from Phase 2

---

## 3.1 Why State Machines?

### Current Problems

```php
// Current: Direct status updates with no validation
$course->update(['status' => 'published']);

// Problems:
// 1. No validation: Can go from archived → published directly
// 2. No audit: Who changed it? When?
// 3. No guards: What if course has no content?
// 4. No events: No way to react to state changes
```

### With State Machines

```php
// After: Validated, audited, event-driven transitions
$course->state->transitionTo(PublishedState::class);

// Benefits:
// 1. Validated: Only valid transitions allowed
// 2. Audited: Transition history tracked
// 3. Guarded: Business rules enforced
// 4. Event-driven: Listeners react to changes
```

---

## 3.2 Package Selection

### Recommended: `spatie/laravel-model-states`

```bash
composer require spatie/laravel-model-states
```

**Why Spatie?**
- Battle-tested, well-maintained
- Native Laravel integration
- Transition hooks (before/after)
- State-based scopes
- Easy testing
- Minimal boilerplate

---

## 3.3 Course State Machine

### State Diagram

```
                    ┌─────────────┐
                    │    Draft    │
                    └──────┬──────┘
                           │
                     publish()
                           │
                           ▼
                    ┌─────────────┐
              ┌────▶│  Published  │◀────┐
              │     └──────┬──────┘     │
              │            │            │
         unpublish()   archive()   reactivate()
              │            │            │
              │            ▼            │
              │     ┌─────────────┐     │
              └─────│  Archived   │─────┘
                    └─────────────┘
```

### States Implementation

```php
<?php
// app/Domain/Course/States/CourseState.php

namespace App\Domain\Course\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class CourseState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, PublishedState::class)
            ->allowTransition(PublishedState::class, DraftState::class)
            ->allowTransition(PublishedState::class, ArchivedState::class)
            ->allowTransition(ArchivedState::class, PublishedState::class)
            ->allowTransition(DraftState::class, ArchivedState::class);
    }
}
```

```php
<?php
// app/Domain/Course/States/DraftState.php

namespace App\Domain\Course\States;

class DraftState extends CourseState
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draf';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function canEdit(): bool
    {
        return true;
    }

    public function canEnroll(): bool
    {
        return false;
    }
}
```

```php
<?php
// app/Domain/Course/States/PublishedState.php

namespace App\Domain\Course\States;

class PublishedState extends CourseState
{
    public static string $name = 'published';

    public function label(): string
    {
        return 'Dipublikasikan';
    }

    public function color(): string
    {
        return 'green';
    }

    public function canEdit(): bool
    {
        return false;
    }

    public function canEnroll(): bool
    {
        return true;
    }
}
```

```php
<?php
// app/Domain/Course/States/ArchivedState.php

namespace App\Domain\Course\States;

class ArchivedState extends CourseState
{
    public static string $name = 'archived';

    public function label(): string
    {
        return 'Diarsipkan';
    }

    public function color(): string
    {
        return 'yellow';
    }

    public function canEdit(): bool
    {
        return false;
    }

    public function canEnroll(): bool
    {
        return false;
    }
}
```

### Transition Classes

```php
<?php
// app/Domain/Course/States/Transitions/PublishCourseTransition.php

namespace App\Domain\Course\States\Transitions;

use App\Domain\Course\Events\CoursePublished;
use App\Domain\Course\Exceptions\CannotPublishException;
use App\Domain\Course\States\PublishedState;
use App\Models\Course;
use Spatie\ModelStates\Transition;

class PublishCourseTransition extends Transition
{
    public function __construct(
        protected Course $course,
        protected ?int $publisherId = null
    ) {}

    public function handle(): Course
    {
        // Validation
        $errors = $this->getValidationErrors();
        if (!empty($errors)) {
            throw new CannotPublishException($this->course->id, $errors);
        }

        // Update model
        $this->course->status = PublishedState::$name;
        $this->course->published_at = now();
        $this->course->published_by = $this->publisherId ?? auth()->id();
        $this->course->save();

        // Dispatch event
        CoursePublished::dispatch(
            $this->course,
            $this->publisherId ?? auth()->id()
        );

        return $this->course;
    }

    protected function getValidationErrors(): array
    {
        $errors = [];

        if (empty($this->course->title)) {
            $errors[] = 'Kursus harus memiliki judul';
        }

        if ($this->course->sections()->count() === 0) {
            $errors[] = 'Kursus harus memiliki minimal satu bagian';
        }

        if ($this->course->lessons()->count() === 0) {
            $errors[] = 'Kursus harus memiliki minimal satu materi';
        }

        if ($this->course->category_id === null) {
            $errors[] = 'Kursus harus memiliki kategori';
        }

        return $errors;
    }
}
```

```php
<?php
// app/Domain/Course/States/Transitions/UnpublishCourseTransition.php

namespace App\Domain\Course\States\Transitions;

use App\Domain\Course\Events\CourseUnpublished;
use App\Domain\Course\States\DraftState;
use App\Models\Course;
use Spatie\ModelStates\Transition;

class UnpublishCourseTransition extends Transition
{
    public function __construct(
        protected Course $course
    ) {}

    public function handle(): Course
    {
        $previousStatus = $this->course->status;

        $this->course->status = DraftState::$name;
        $this->course->published_at = null;
        $this->course->published_by = null;
        $this->course->save();

        CourseUnpublished::dispatch($this->course, $previousStatus);

        return $this->course;
    }
}
```

### Model Integration

```php
<?php
// app/Models/Course.php (updated)

namespace App\Models;

use App\Domain\Course\States\CourseState;
use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\PublishedState;
use App\Domain\Course\States\ArchivedState;
use App\Domain\Course\States\Transitions\PublishCourseTransition;
use App\Domain\Course\States\Transitions\UnpublishCourseTransition;
use Spatie\ModelStates\HasStates;
// ... other imports

class Course extends Model
{
    use HasFactory, SoftDeletes, HasStates;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        // ... other fields
        'status', // Keep for now, will be managed by state
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseState::class, // Add state cast
            'objectives' => 'array',
            'prerequisites' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // State helper methods
    public function isDraft(): bool
    {
        return $this->status instanceof DraftState;
    }

    public function isPublished(): bool
    {
        return $this->status instanceof PublishedState;
    }

    public function isArchived(): bool
    {
        return $this->status instanceof ArchivedState;
    }

    public function canBeEdited(): bool
    {
        return $this->status->canEdit();
    }

    public function canAcceptEnrollments(): bool
    {
        return $this->status->canEnroll();
    }

    // Transition methods
    public function publish(?int $publisherId = null): self
    {
        return $this->status->transitionTo(
            PublishedState::class,
            new PublishCourseTransition($this, $publisherId)
        );
    }

    public function unpublish(): self
    {
        return $this->status->transitionTo(
            DraftState::class,
            new UnpublishCourseTransition($this)
        );
    }

    public function archive(): self
    {
        return $this->status->transitionTo(ArchivedState::class);
    }

    // ... other methods remain
}
```

---

## 3.4 Enrollment State Machine

### State Diagram

```
                    ┌─────────────┐
                    │   Active    │
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
          complete()    drop()      expire()
              │            │            │
              ▼            ▼            ▼
        ┌─────────┐  ┌─────────┐  ┌─────────┐
        │Completed│  │ Dropped │  │ Expired │
        └─────────┘  └─────────┘  └─────────┘
```

### States Implementation

```php
<?php
// app/Domain/Enrollment/States/EnrollmentState.php

namespace App\Domain\Enrollment\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EnrollmentState extends State
{
    abstract public function label(): string;

    abstract public function canAccessContent(): bool;

    abstract public function canTrackProgress(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActiveState::class)
            ->allowTransition(ActiveState::class, CompletedState::class)
            ->allowTransition(ActiveState::class, DroppedState::class)
            ->allowTransition(ActiveState::class, ExpiredState::class)
            // Allow reactivation
            ->allowTransition(DroppedState::class, ActiveState::class)
            ->allowTransition(ExpiredState::class, ActiveState::class);
    }
}
```

```php
<?php
// app/Domain/Enrollment/States/ActiveState.php

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
```

```php
<?php
// app/Domain/Enrollment/States/CompletedState.php

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
```

```php
<?php
// app/Domain/Enrollment/States/DroppedState.php

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
```

### Transition Classes

```php
<?php
// app/Domain/Enrollment/States/Transitions/CompleteEnrollmentTransition.php

namespace App\Domain\Enrollment\States\Transitions;

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\Enrollment\States\CompletedState;
use App\Models\Enrollment;
use Spatie\ModelStates\Transition;

class CompleteEnrollmentTransition extends Transition
{
    public function __construct(
        protected Enrollment $enrollment
    ) {}

    public function handle(): Enrollment
    {
        $this->enrollment->status = CompletedState::$name;
        $this->enrollment->completed_at = now();
        $this->enrollment->save();

        EnrollmentCompleted::dispatch($this->enrollment);

        return $this->enrollment;
    }
}
```

---

## 3.5 Assessment Attempt State Machine

### State Diagram

```
                    ┌─────────────────┐
                    │   In Progress   │
                    └────────┬────────┘
                             │
                ┌────────────┼────────────┐
                │            │            │
            submit()     timeout()    cancel()
                │            │            │
                ▼            ▼            ▼
          ┌──────────┐ ┌──────────┐ ┌──────────┐
          │Submitted │ │ Expired  │ │Cancelled │
          └────┬─────┘ └──────────┘ └──────────┘
               │
          grade() (auto or manual)
               │
               ▼
          ┌──────────┐
          │  Graded  │
          └────┬─────┘
               │
          complete()
               │
               ▼
          ┌──────────┐
          │Completed │
          └──────────┘
```

### States Implementation

```php
<?php
// app/Domain/Assessment/States/AttemptState.php

namespace App\Domain\Assessment\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class AttemptState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function canSubmit(): bool;

    abstract public function canBeGraded(): bool;

    abstract public function isFinalized(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(InProgressState::class)
            // From In Progress
            ->allowTransition(InProgressState::class, SubmittedState::class)
            ->allowTransition(InProgressState::class, ExpiredState::class)
            ->allowTransition(InProgressState::class, CancelledState::class)
            // From Submitted
            ->allowTransition(SubmittedState::class, GradedState::class)
            // From Graded
            ->allowTransition(GradedState::class, CompletedState::class)
            // Auto-grade can skip submitted
            ->allowTransition(InProgressState::class, GradedState::class);
    }
}
```

```php
<?php
// app/Domain/Assessment/States/InProgressState.php

namespace App\Domain\Assessment\States;

class InProgressState extends AttemptState
{
    public static string $name = 'in_progress';

    public function label(): string
    {
        return 'Sedang Dikerjakan';
    }

    public function color(): string
    {
        return 'blue';
    }

    public function canSubmit(): bool
    {
        return true;
    }

    public function canBeGraded(): bool
    {
        return false;
    }

    public function isFinalized(): bool
    {
        return false;
    }
}
```

```php
<?php
// app/Domain/Assessment/States/SubmittedState.php

namespace App\Domain\Assessment\States;

class SubmittedState extends AttemptState
{
    public static string $name = 'submitted';

    public function label(): string
    {
        return 'Diserahkan';
    }

    public function color(): string
    {
        return 'yellow';
    }

    public function canSubmit(): bool
    {
        return false;
    }

    public function canBeGraded(): bool
    {
        return true;
    }

    public function isFinalized(): bool
    {
        return false;
    }
}
```

```php
<?php
// app/Domain/Assessment/States/GradedState.php

namespace App\Domain\Assessment\States;

class GradedState extends AttemptState
{
    public static string $name = 'graded';

    public function label(): string
    {
        return 'Dinilai';
    }

    public function color(): string
    {
        return 'purple';
    }

    public function canSubmit(): bool
    {
        return false;
    }

    public function canBeGraded(): bool
    {
        return false;
    }

    public function isFinalized(): bool
    {
        return true;
    }
}
```

```php
<?php
// app/Domain/Assessment/States/CompletedState.php

namespace App\Domain\Assessment\States;

class CompletedState extends AttemptState
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Selesai';
    }

    public function color(): string
    {
        return 'green';
    }

    public function canSubmit(): bool
    {
        return false;
    }

    public function canBeGraded(): bool
    {
        return false;
    }

    public function isFinalized(): bool
    {
        return true;
    }
}
```

### Transition with Guards

```php
<?php
// app/Domain/Assessment/States/Transitions/SubmitAttemptTransition.php

namespace App\Domain\Assessment\States\Transitions;

use App\Domain\Assessment\Events\AssessmentSubmitted;
use App\Domain\Assessment\Exceptions\CannotSubmitException;
use App\Domain\Assessment\States\SubmittedState;
use App\Domain\Assessment\States\GradedState;
use App\Models\AssessmentAttempt;
use Spatie\ModelStates\Transition;

class SubmitAttemptTransition extends Transition
{
    public function __construct(
        protected AssessmentAttempt $attempt,
        protected bool $autoGraded = false,
        protected ?float $score = null,
        protected ?float $percentage = null,
        protected ?bool $passed = null
    ) {}

    public function canTransition(): bool
    {
        // Guard: Can only submit in progress attempts
        if (!$this->attempt->status->canSubmit()) {
            return false;
        }

        // Guard: Must have at least one answer
        if ($this->attempt->answers()->count() === 0) {
            return false;
        }

        return true;
    }

    public function handle(): AssessmentAttempt
    {
        if (!$this->canTransition()) {
            throw new CannotSubmitException(
                $this->attempt->id,
                'Attempt cannot be submitted in current state'
            );
        }

        // Determine target state
        $targetState = $this->autoGraded ? GradedState::$name : SubmittedState::$name;

        $this->attempt->status = $targetState;
        $this->attempt->submitted_at = now();

        if ($this->autoGraded) {
            $this->attempt->score = $this->score;
            $this->attempt->percentage = $this->percentage;
            $this->attempt->passed = $this->passed;
            $this->attempt->graded_at = now();
        }

        $this->attempt->save();

        AssessmentSubmitted::dispatch($this->attempt);

        return $this->attempt;
    }
}
```

---

## 3.6 State-Based Querying

### Eloquent Scopes

```php
<?php
// app/Models/Course.php (add scopes)

// State-based scopes (using Spatie)
public function scopeWhereState(Builder $query, string|array $states): Builder
{
    if (is_array($states)) {
        return $query->whereIn('status', $states);
    }

    return $query->where('status', $states);
}

public function scopeWhereNotState(Builder $query, string|array $states): Builder
{
    if (is_array($states)) {
        return $query->whereNotIn('status', $states);
    }

    return $query->where('status', '!=', $states);
}

// Convenience scopes
public function scopePublished(Builder $query): Builder
{
    return $query->whereState('published');
}

public function scopeDraft(Builder $query): Builder
{
    return $query->whereState('draft');
}

public function scopeVisible(Builder $query): Builder
{
    return $query->whereState(['published'])
        ->where('visibility', '!=', 'hidden');
}
```

### Usage Examples

```php
// Get all published courses
$publishedCourses = Course::published()->get();

// Get courses that can accept enrollments
$enrollableCourses = Course::all()->filter(
    fn($course) => $course->status->canEnroll()
);

// Get active enrollments
$activeEnrollments = Enrollment::whereState('active')->get();

// Get attempts requiring grading
$pendingGrading = AssessmentAttempt::whereState('submitted')
    ->with('assessment', 'user')
    ->get();
```

---

## 3.7 State Transition Logging

### Migration for Audit Log

```php
<?php
// database/migrations/xxxx_create_state_transitions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_transitions', function (Blueprint $table) {
            $table->id();
            $table->morphs('transitionable'); // model_type, model_id
            $table->string('from_state');
            $table->string('to_state');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('transitioned_at');
            $table->index(['transitionable_type', 'transitionable_id', 'transitioned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_transitions');
    }
};
```

### Transition Logger

```php
<?php
// app/Domain/Shared/Services/StateTransitionLogger.php

namespace App\Domain\Shared\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StateTransitionLogger
{
    public function log(
        Model $model,
        string $fromState,
        string $toState,
        ?int $userId = null,
        array $metadata = []
    ): void {
        DB::table('state_transitions')->insert([
            'transitionable_type' => $model->getMorphClass(),
            'transitionable_id' => $model->getKey(),
            'from_state' => $fromState,
            'to_state' => $toState,
            'user_id' => $userId ?? auth()->id(),
            'metadata' => json_encode($metadata),
            'transitioned_at' => now(),
        ]);
    }

    public function getHistory(Model $model): array
    {
        return DB::table('state_transitions')
            ->where('transitionable_type', $model->getMorphClass())
            ->where('transitionable_id', $model->getKey())
            ->orderBy('transitioned_at', 'desc')
            ->get()
            ->toArray();
    }
}
```

### Trait for Models with Transition History

```php
<?php
// app/Domain/Shared/Concerns/HasStateTransitionHistory.php

namespace App\Domain\Shared\Concerns;

use App\Domain\Shared\Services\StateTransitionLogger;

trait HasStateTransitionHistory
{
    public function logStateTransition(
        string $fromState,
        string $toState,
        array $metadata = []
    ): void {
        app(StateTransitionLogger::class)->log(
            $this,
            $fromState,
            $toState,
            auth()->id(),
            $metadata
        );
    }

    public function getStateTransitionHistory(): array
    {
        return app(StateTransitionLogger::class)->getHistory($this);
    }
}
```

---

## 3.8 Service Integration

### Updated CoursePublishingService

```php
<?php
// app/Domain/Course/Services/CoursePublishingService.php (updated)

namespace App\Domain\Course\Services;

use App\Domain\Course\Contracts\CoursePublishingServiceContract;
use App\Domain\Course\DTOs\PublishResult;
use App\Domain\Course\States\PublishedState;
use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\ArchivedState;
use App\Domain\Course\States\Transitions\PublishCourseTransition;
use App\Domain\Course\States\Transitions\UnpublishCourseTransition;
use App\Domain\Shared\Services\StateTransitionLogger;
use App\Models\Course;
use App\Models\User;

class CoursePublishingService implements CoursePublishingServiceContract
{
    public function __construct(
        protected StateTransitionLogger $transitionLogger
    ) {}

    public function publish(Course $course, User $publisher): PublishResult
    {
        $previousStatus = $course->status::$name;

        // Use state machine transition
        $course->status->transitionTo(
            PublishedState::class,
            new PublishCourseTransition($course, $publisher->id)
        );

        // Log transition
        $this->transitionLogger->log(
            $course,
            $previousStatus,
            PublishedState::$name,
            $publisher->id,
            ['action' => 'publish']
        );

        return new PublishResult(
            course: $course->fresh(),
            previousStatus: $previousStatus,
            newStatus: PublishedState::$name,
        );
    }

    public function unpublish(Course $course): PublishResult
    {
        $previousStatus = $course->status::$name;

        $course->status->transitionTo(
            DraftState::class,
            new UnpublishCourseTransition($course)
        );

        $this->transitionLogger->log(
            $course,
            $previousStatus,
            DraftState::$name,
            auth()->id(),
            ['action' => 'unpublish']
        );

        return new PublishResult(
            course: $course->fresh(),
            previousStatus: $previousStatus,
            newStatus: DraftState::$name,
        );
    }

    public function archive(Course $course): PublishResult
    {
        $previousStatus = $course->status::$name;

        $course->status->transitionTo(ArchivedState::class);

        $this->transitionLogger->log(
            $course,
            $previousStatus,
            ArchivedState::$name,
            auth()->id(),
            ['action' => 'archive']
        );

        return new PublishResult(
            course: $course->fresh(),
            previousStatus: $previousStatus,
            newStatus: ArchivedState::$name,
        );
    }

    public function canPublish(Course $course): bool
    {
        // Check if transition is allowed
        if (!$course->status->canTransitionTo(PublishedState::class)) {
            return false;
        }

        // Check business rules
        return empty($this->getPublishValidationErrors($course));
    }

    public function getPublishValidationErrors(Course $course): array
    {
        $errors = [];

        if (empty($course->title)) {
            $errors[] = 'Kursus harus memiliki judul';
        }

        if ($course->sections()->count() === 0) {
            $errors[] = 'Kursus harus memiliki minimal satu bagian';
        }

        if ($course->lessons()->count() === 0) {
            $errors[] = 'Kursus harus memiliki minimal satu materi';
        }

        if ($course->category_id === null) {
            $errors[] = 'Kursus harus memiliki kategori';
        }

        return $errors;
    }
}
```

---

## 3.9 Testing State Machines

### State Transition Tests

```php
<?php
// tests/Unit/Domain/Course/States/CourseStateTest.php

use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\PublishedState;
use App\Domain\Course\States\ArchivedState;
use App\Models\Course;
use App\Models\Category;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CourseState', function () {
    beforeEach(function () {
        $this->category = Category::factory()->create();
        $this->course = Course::factory()->create([
            'status' => 'draft',
            'category_id' => $this->category->id,
        ]);

        // Add required content for publishing
        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        Lesson::factory()->create(['course_section_id' => $section->id]);
    });

    it('starts in draft state', function () {
        expect($this->course->status)->toBeInstanceOf(DraftState::class);
        expect($this->course->isDraft())->toBeTrue();
    });

    it('can transition from draft to published', function () {
        $this->course->publish(auth()->id());

        expect($this->course->fresh()->status)->toBeInstanceOf(PublishedState::class);
        expect($this->course->fresh()->isPublished())->toBeTrue();
    });

    it('can transition from published to draft', function () {
        $this->course->publish();
        $this->course->unpublish();

        expect($this->course->fresh()->status)->toBeInstanceOf(DraftState::class);
    });

    it('can transition from published to archived', function () {
        $this->course->publish();
        $this->course->archive();

        expect($this->course->fresh()->status)->toBeInstanceOf(ArchivedState::class);
    });

    it('cannot transition from draft to archived directly if not allowed', function () {
        // This test depends on your state config
        // If you allow draft -> archived, adjust accordingly
        $this->course->archive();

        expect($this->course->fresh()->isArchived())->toBeTrue();
    });

    it('prevents invalid state transitions', function () {
        // Archived -> Published not allowed (unless configured)
        $this->course->publish();
        $this->course->archive();

        // Try to go back to published
        $this->course->status->transitionTo(PublishedState::class);
    })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);
});
```

### Transition Guard Tests

```php
<?php
// tests/Unit/Domain/Course/States/PublishCourseTransitionTest.php

use App\Domain\Course\Exceptions\CannotPublishException;
use App\Domain\Course\States\Transitions\PublishCourseTransition;
use App\Models\Course;
use App\Models\Category;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PublishCourseTransition', function () {
    it('fails without title', function () {
        $course = Course::factory()->create([
            'title' => '',
            'status' => 'draft',
        ]);

        $transition = new PublishCourseTransition($course);
        $transition->handle();
    })->throws(CannotPublishException::class);

    it('fails without sections', function () {
        $course = Course::factory()->create([
            'status' => 'draft',
            'category_id' => Category::factory()->create()->id,
        ]);

        $transition = new PublishCourseTransition($course);
        $transition->handle();
    })->throws(CannotPublishException::class);

    it('fails without lessons', function () {
        $course = Course::factory()->create([
            'status' => 'draft',
            'category_id' => Category::factory()->create()->id,
        ]);
        CourseSection::factory()->create(['course_id' => $course->id]);

        $transition = new PublishCourseTransition($course);
        $transition->handle();
    })->throws(CannotPublishException::class);

    it('succeeds with all requirements met', function () {
        $course = Course::factory()->create([
            'status' => 'draft',
            'category_id' => Category::factory()->create()->id,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['course_section_id' => $section->id]);

        $transition = new PublishCourseTransition($course);
        $result = $transition->handle();

        expect($result->status::$name)->toBe('published');
        expect($result->published_at)->not->toBeNull();
    });
});
```

---

## 3.10 Implementation Checklist

### Week 6: Course & Enrollment State Machines

- [ ] Install spatie/laravel-model-states
- [ ] Course State Machine
  - [ ] Create CourseState base class
  - [ ] Create DraftState, PublishedState, ArchivedState
  - [ ] Create transition classes
  - [ ] Update Course model
  - [ ] Write tests
- [ ] Enrollment State Machine
  - [ ] Create EnrollmentState base class
  - [ ] Create ActiveState, CompletedState, DroppedState
  - [ ] Create transition classes
  - [ ] Update Enrollment model
  - [ ] Write tests

### Week 7: Assessment & Infrastructure

- [ ] AssessmentAttempt State Machine
  - [ ] Create AttemptState base class
  - [ ] Create all state classes
  - [ ] Create transition classes
  - [ ] Update AssessmentAttempt model
  - [ ] Write tests
- [ ] State Transition Logging
  - [ ] Create migration
  - [ ] Create StateTransitionLogger service
  - [ ] Create HasStateTransitionHistory trait
  - [ ] Integrate with services
- [ ] Update Services
  - [ ] Update CoursePublishingService
  - [ ] Update EnrollmentService
  - [ ] Update GradingService

---

## Next Phase

Once Phase 3 is complete, proceed to [Phase 4: Event-Driven Architecture](./04-EVENT-DRIVEN.md).

State machines are now emitting events, which Phase 4 will handle with listeners for notifications, logging, and integrations.
