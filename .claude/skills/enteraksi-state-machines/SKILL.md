---
name: enteraksi-state-machines
description: State machine patterns using Spatie/model-states for Enteraksi LMS. Use when implementing status workflows, state transitions, or adding state-based business rules.
triggers:
  - state machine
  - model state
  - status transition
  - draft published archived
  - active completed dropped
  - spatie states
  - state pattern
  - canEdit
  - canEnroll
  - isPublished
  - isDraft
  - status workflow
---

# Enteraksi State Machines

## When to Use This Skill

- Implementing status workflows (draft/published/archived)
- Adding state-based business rules (canEdit, canEnroll)
- Creating state transitions with validation
- Adding helper methods to check state
- Working with spatie/laravel-model-states package

## Existing State Machines

| Entity | States | Transitions |
|--------|--------|-------------|
| Course | Draft → Published → Archived | Draft ↔ Published, Published → Archived, Archived → Draft/Published |
| Enrollment | Active → Completed/Dropped | Active → Completed, Active → Dropped, Dropped → Active |
| LearningPathEnrollment | Active → Completed/Dropped | Same as Enrollment |
| LearningPathCourseProgress | Locked → Available → InProgress → Completed | Sequential flow |

## Key Patterns

### 1. Abstract State Base Class

```php
// app/Domain/Course/States/CourseState.php
namespace App\Domain\Course\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class CourseState extends State
{
    /**
     * Human-readable label for UI.
     */
    abstract public function label(): string;

    /**
     * Color for badges/tags (gray, green, yellow, red).
     */
    abstract public function color(): string;

    /**
     * Business rule: can course be edited?
     */
    abstract public function canEdit(): bool;

    /**
     * Business rule: can users enroll?
     */
    abstract public function canEnroll(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            // Draft -> Published
            ->allowTransition(DraftState::class, PublishedState::class)
            // Published -> Draft (unpublish)
            ->allowTransition(PublishedState::class, DraftState::class)
            // Published -> Archived
            ->allowTransition(PublishedState::class, ArchivedState::class)
            // Draft -> Archived
            ->allowTransition(DraftState::class, ArchivedState::class)
            // Archived -> Published (reactivate)
            ->allowTransition(ArchivedState::class, PublishedState::class)
            // Archived -> Draft
            ->allowTransition(ArchivedState::class, DraftState::class);
    }
}
```

### 2. Concrete State Classes

```php
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
        return false;  // Published courses can't be edited
    }

    public function canEnroll(): bool
    {
        return true;
    }
}
```

### 3. Model Integration

```php
// app/Models/Course.php
namespace App\Models;

use App\Domain\Course\States\ArchivedState;
use App\Domain\Course\States\CourseState;
use App\Domain\Course\States\DraftState;
use App\Domain\Course\States\PublishedState;
use Spatie\ModelStates\HasStates;

class Course extends Model
{
    use HasStates;

    protected function casts(): array
    {
        return [
            'status' => CourseState::class,  // Cast to state class
            // other casts...
        ];
    }

    // ==========================================================================
    // State Helper Methods - Check current state
    // ==========================================================================

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

    // ==========================================================================
    // Business Rules - Delegate to state
    // ==========================================================================

    public function canBeEdited(): bool
    {
        return $this->status->canEdit();
    }

    public function canAcceptEnrollments(): bool
    {
        return $this->status->canEnroll();
    }
}
```

### 4. State Transition with Events

```php
// In a service class
use App\Domain\Course\Events\CoursePublished;

public function publish(Course $course): void
{
    DB::transaction(function () use ($course) {
        // Spatie validates transition automatically
        $course->status->transitionTo(PublishedState::class);

        $course->update([
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        CoursePublished::dispatch($course);
    });
}
```

### 5. Enrollment State Example

```php
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
            ->allowTransition(DroppedState::class, ActiveState::class);
    }
}

// ActiveState.php
class ActiveState extends EnrollmentState
{
    public static string $name = 'active';

    public function label(): string { return 'Aktif'; }
    public function canAccessContent(): bool { return true; }
    public function canTrackProgress(): bool { return true; }
}

// CompletedState.php
class CompletedState extends EnrollmentState
{
    public static string $name = 'completed';

    public function label(): string { return 'Selesai'; }
    public function canAccessContent(): bool { return true; }
    public function canTrackProgress(): bool { return false; }
}

// DroppedState.php
class DroppedState extends EnrollmentState
{
    public static string $name = 'dropped';

    public function label(): string { return 'Keluar'; }
    public function canAccessContent(): bool { return false; }
    public function canTrackProgress(): bool { return false; }
}
```

## State Colors Reference

| State | Color | Tailwind Badge |
|-------|-------|----------------|
| Draft | `gray` | `bg-gray-100 text-gray-800` |
| Published/Active | `green` | `bg-green-100 text-green-800` |
| Archived | `yellow` | `bg-yellow-100 text-yellow-800` |
| Completed | `blue` | `bg-blue-100 text-blue-800` |
| Dropped | `red` | `bg-red-100 text-red-800` |

## Gotchas & Best Practices

1. **Always define `$name` property** - Used for database storage
2. **Use `instanceof` for state checks** - Not string comparison
3. **Delegate business rules to state** - `$model->status->canEdit()` not `if ($model->status === 'draft')`
4. **Wrap transitions in DB::transaction()** - Especially when dispatching events
5. **Invalid transitions throw `TransitionNotAllowed`** - Handle in controller
6. **State is cast automatically** - Query with string: `where('status', 'published')`

## Query Examples

```php
// Query by state (use string name)
Course::where('status', 'published')->get();

// Query with scope
Course::query()
    ->whereIn('status', [DraftState::$name, PublishedState::$name])
    ->get();

// Check state in policy
public function update(User $user, Course $course): bool
{
    return $user->id === $course->user_id
        && $course->canBeEdited();  // Delegates to state
}
```

## Quick Reference

```bash
# Files to reference
app/Domain/Course/States/CourseState.php           # Base state class
app/Domain/Course/States/DraftState.php            # Concrete state
app/Domain/Enrollment/States/EnrollmentState.php   # Another example
app/Models/Course.php                              # Model integration

# Required trait
use Spatie\ModelStates\HasStates;

# Required cast
'status' => CourseState::class
```
