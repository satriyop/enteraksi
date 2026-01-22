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

## Cascade Authorization Pattern

When child resources (sections, lessons) delegate authorization to parent (course), fixing the parent policy automatically fixes all children. This is a powerful pattern.

### Role × State Authorization Matrix

| Role | Draft Course | Published Course |
|------|--------------|------------------|
| LMS Admin | ✅ Edit/Delete | ✅ Edit/Delete |
| Content Manager (owner) | ✅ Edit/Delete | ❌ Frozen |
| Content Manager (other) | ❌ No access | ❌ No access |
| Learner | ❌ No access | ❌ No access |

### Policy Implementation

```php
// app/Policies/CoursePolicy.php
public function update(User $user, Course $course): bool
{
    // LMS Admin can always edit any course (draft or published)
    if ($user->isLmsAdmin()) {
        return true;
    }

    // Content manager can only edit their own DRAFT courses
    // Published courses are "frozen" - must ask admin to unpublish first
    if ($course->user_id === $user->id && $user->canManageCourses()) {
        return $course->isDraft();  // ← Key: state check here
    }

    return false;
}
```

### Child Resource Policy Delegation

```php
// app/Policies/CourseSectionPolicy.php
public function create(User $user, Course $course): bool
{
    // Delegate to course update permission
    return Gate::allows('update', $course);  // ← Automatic cascade
}

public function delete(User $user, CourseSection $section): bool
{
    return Gate::allows('update', $section->course);
}

// app/Policies/LessonPolicy.php - same pattern
public function create(User $user, CourseSection $section): bool
{
    return Gate::allows('update', $section->course);
}
```

### Why This Works

By checking `$course->isDraft()` in `CoursePolicy::update()`:
- `CourseSectionPolicy::create()` → denied for published courses
- `CourseSectionPolicy::delete()` → denied for published courses
- `LessonPolicy::create()` → denied for published courses
- `LessonPolicy::delete()` → denied for published courses

**One fix in the parent policy cascades to ALL child resources.**

### Testing the Cascade

```php
// Test parent policy restriction cascades to children
it('content manager cannot add section to published course', function () {
    $cm = User::factory()->create(['role' => 'content_manager']);
    $course = Course::factory()->published()->create(['user_id' => $cm->id]);

    $this->actingAs($cm)
        ->post("/courses/{$course->id}/sections", ['title' => 'New Section'])
        ->assertForbidden();  // Not 302 redirect
});

it('content manager cannot add lesson to published course', function () {
    $cm = User::factory()->create(['role' => 'content_manager']);
    $course = Course::factory()->published()->create(['user_id' => $cm->id]);
    $section = CourseSection::factory()->create(['course_id' => $course->id]);

    $this->actingAs($cm)
        ->post("/sections/{$section->id}/lessons", ['title' => 'New Lesson'])
        ->assertForbidden();
});
```

## Gotchas & Best Practices

1. **Always define `$name` property** - Used for database storage
2. **Use `instanceof` for state checks** - Not string comparison
3. **Delegate business rules to state** - `$model->status->canEdit()` not `if ($model->status === 'draft')`
4. **Wrap transitions in DB::transaction()** - Especially when dispatching events
5. **Invalid transitions throw `TransitionNotAllowed`** - Handle in controller
6. **State is cast automatically** - Query with string: `where('status', 'published')`
7. **Use cascade authorization** - Child policies delegate to parent, so one fix cascades to all
8. **Role × State matrix** - Always consider both role AND state when authorizing actions
9. **Use `::$name` consistently for queries** - Never mix hardcoded strings with constants in the same file

### Gotcha #9: Inconsistent State String Usage

```php
// ❌ BAD: Mixing constants and hardcoded strings in same file
$active = $enrollment->courseProgress()
    ->where('state', CompletedCourseState::$name)  // Uses constant
    ->count();

$completed = $enrollment->courseProgress()
    ->where('state', 'completed')  // Hardcoded string!
    ->count();

// ✅ GOOD: Consistent usage throughout
$active = $enrollment->courseProgress()
    ->where('state', CompletedCourseState::$name)
    ->count();

$completed = $enrollment->courseProgress()
    ->where('state', CompletedCourseState::$name)
    ->count();
```

**Why it matters:**
- IDE "find usages" won't find hardcoded strings
- Typos like `'complted'` silently fail (returns 0 rows, no error)
- If state name ever changes, hardcoded strings break
- Code review harder - inconsistent patterns confuse developers

---

## CRITICAL: State Mutation Bug

### The Problem

Direct string assignment to a Spatie state-casted column **corrupts the state object**.

```php
// ❌ WRONG - Corrupts state! Will cause "Call to member function on string"
$enrollment->state = ActivePathState::$name;  // Assigns string "active"
$enrollment->completed_at = null;
$enrollment->save();

// After save():
// - $enrollment->state is now a STRING, not a state instance!
// - $enrollment->isActive() returns FALSE (instanceof check fails)
// - $enrollment->state->canAccessContent() throws TypeError
```

### Why It Breaks

1. `state` column is cast to `PathEnrollmentState::class` (Spatie state machine)
2. Assigning `ActivePathState::$name` (a string) **bypasses** the state casting
3. After `save()`, the model holds a raw string instead of state instance
4. `$enrollment->isActive()` checks `$this->state instanceof ActivePathState` → **FALSE** (it's a string!)
5. Any `$enrollment->state->method()` call → **"Call to member function on string"**

### Impact

- **Data Corruption**: Enrollments become "zombies" with invalid state
- **App Crashes**: Any subsequent state checks throw errors
- **Silent Failures**: Queued listeners errors aren't visible during user flow
- **Cascading Failures**: Other listeners checking state will misbehave

### The Fix: Use update() or transitionTo()

**Option 1: Use `update()` with state class (Recommended)**

```php
// ✅ CORRECT - update() triggers attribute casting
$updateData = ['progress_percentage' => $newPercentage];

if ($enrollment->isCompleted()) {
    $updateData['state'] = ActivePathState::class;  // Pass CLASS, not $name
    $updateData['completed_at'] = null;
}

$enrollment->update($updateData);
// Now $enrollment->state is properly cast to ActivePathState instance
```

**Option 2: Use `transitionTo()` for explicit transitions**

```php
// ✅ CORRECT - Spatie handles the transition properly
if ($enrollment->isCompleted()) {
    $enrollment->state->transitionTo(ActivePathState::class);
    $enrollment->completed_at = null;
    $enrollment->save();
}
```

### Real Example from Enteraksi (Fixed)

```php
// app/Domain/LearningPath/Listeners/UpdatePathProgressOnCourseDrop.php

// ❌ BEFORE (BAD):
if ($pathEnrollment->isCompleted()) {
    $pathEnrollment->state = ActivePathState::$name;  // String assignment!
    $pathEnrollment->completed_at = null;
}
$pathEnrollment->save();

// ✅ AFTER (FIXED):
$updateData = ['progress_percentage' => $newPercentage];
if ($pathEnrollment->isCompleted()) {
    $updateData['state'] = ActivePathState::class;  // Class, not $name
    $updateData['completed_at'] = null;
}
$pathEnrollment->update($updateData);
```

### Detection: Search for State Bugs

```bash
# Find direct state assignments (potential bugs)
grep -r "->state = " app/

# Find correct patterns (using class)
grep -r "State::class" app/
```

### Rule of Thumb

| Action | Correct Approach |
|--------|------------------|
| Update state column | `->update(['state' => NewState::class])` |
| Explicit transition | `->state->transitionTo(NewState::class)` |
| Query by state | `->where('state', NewState::$name)` (prefer constant over hardcoded string) |
| Check current state | `$model->state instanceof NewState` or `$model->isActive()` |

**NEVER** do `$model->state = SomeState::$name` followed by `->save()`!

**ALWAYS** use `::$name` constants for queries - never hardcode `'completed'` or `'active'` strings.

## Query Examples

```php
// Query by state (prefer ::$name constant for consistency)
Course::where('status', PublishedState::$name)->get();

// Query with multiple states
Course::query()
    ->whereIn('status', [DraftState::$name, PublishedState::$name])
    ->get();

// Check state in policy
public function update(User $user, Course $course): bool
{
    return $user->id === $course->user_id
        && $course->canBeEdited();  // Delegates to state
}

// Related model queries - always use constants
$completedCourses = $enrollment->courseProgress()
    ->where('state', CompletedCourseState::$name)  // ✅ Good
    // ->where('state', 'completed')  // ❌ Avoid hardcoded strings
    ->count();
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
