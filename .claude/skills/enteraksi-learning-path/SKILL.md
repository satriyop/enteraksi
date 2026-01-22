---
name: enteraksi-learning-path
description: Parent-child enrollment patterns, cross-domain event sync, re-enrollment handling, and progress calculation for Learning Path feature in Enteraksi LMS. Use when working on hierarchical enrollment systems, progress tracking across related entities, or state synchronization between domains.
---

# Enteraksi LMS - Learning Path Enrollment Patterns

Patterns for managing parent-child enrollment hierarchies, cross-domain state synchronization, and progress tracking with required vs optional items.

---

## Core Concept: Parent-Child Enrollment Hierarchy

```
LearningPath (parent)
├── LearningPathEnrollment (parent enrollment)
│   └── LearningPathCourseProgress[] (tracks each course in path)
│       └── course_enrollment_id → Enrollment (child enrollment)
│
Course (child)
└── Enrollment (child enrollment - actual course access)
```

**Key Insight:** Path enrollment doesn't grant course access. Each course needs its own `Enrollment` record for the learner to access content.

---

## Pattern 1: Cascade Enrollment

**Context:** When user enrolls in parent entity, auto-create child enrollments for available items.

### Implementation

```php
// PathEnrollmentService.php
public function enroll(User $user, LearningPath $path): PathEnrollmentResult
{
    $pathEnrollment = $this->createPathEnrollment($user, $path);
    $this->initializeCourseProgress($pathEnrollment);

    return new PathEnrollmentResult($pathEnrollment, isNewEnrollment: true);
}

protected function initializeCourseProgress(LearningPathEnrollment $enrollment): void
{
    $courses = $enrollment->learningPath->courses()
        ->orderBy('learning_path_course.position')
        ->get();

    foreach ($courses as $index => $course) {
        $isFirstCourse = ($index === 0);
        $state = $isFirstCourse ? AvailableCourseState::$name : LockedCourseState::$name;

        // CASCADE: Create child enrollment for available courses
        $courseEnrollment = $isFirstCourse
            ? $this->ensureCourseEnrollment($enrollment->user, $course)
            : null;

        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'course_enrollment_id' => $courseEnrollment?->id,  // Link!
            'state' => $state,
            'position' => $index + 1,
            'unlocked_at' => $isFirstCourse ? now() : null,
        ]);
    }
}
```

### Ensure-or-Reuse Pattern

**Always check for existing before creating:**

```php
protected function ensureCourseEnrollment(User $user, Course $course): Enrollment
{
    // Try to find existing active enrollment
    $existing = $this->enrollmentService->getActiveEnrollment($user, $course);

    if ($existing) {
        $this->logger->info('enrollment.reused', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        return $existing;
    }

    // Create new enrollment
    $result = $this->enrollmentService->enroll(new CreateEnrollmentDTO(
        userId: $user->id,
        courseId: $course->id,
    ));

    return $result->enrollment;
}
```

---

## Pattern 2: Cross-Domain Event Synchronization

**Context:** When state changes in Domain A (Enrollment), Domain B (LearningPath) must update accordingly.

### The Problem

```
User drops Course Enrollment → LearningPathCourseProgress still shows "completed"
                             → Path shows 100% but course is actually dropped
```

### Solution: Queued Listener

```php
// app/Domain/LearningPath/Listeners/UpdatePathProgressOnCourseDrop.php
class UpdatePathProgressOnCourseDrop implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'progress';

    public function __construct(
        protected PathProgressServiceContract $progressService,
        protected DomainLogger $logger
    ) {}

    public function handle(UserDropped $event): void
    {
        // Find all path progress records linked to this enrollment
        $affectedProgress = LearningPathCourseProgress::where(
            'course_enrollment_id', $event->enrollment->id
        )->get();

        foreach ($affectedProgress as $progress) {
            $pathEnrollment = $progress->enrollment;
            $wasCompleted = $progress->isCompleted();

            // Revert course state to available (can re-enroll)
            $progress->update([
                'state' => AvailableCourseState::$name,
                'completed_at' => null,
            ]);

            // Recalculate parent progress
            $newPercentage = $this->progressService->calculateProgressPercentage($pathEnrollment);
            $pathEnrollment->update(['progress_percentage' => $newPercentage]);

            // Revert completed path to active if needed
            if ($pathEnrollment->isCompleted() && $wasCompleted) {
                $pathEnrollment->update([
                    'state' => ActivePathState::$name,
                    'completed_at' => null,
                ]);
            }
        }
    }
}
```

### Register in EventServiceProvider

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    UserDropped::class => [
        LogDomainEvent::class,
        UpdatePathProgressOnCourseDrop::class,  // Cross-domain sync
    ],
];
```

**Key Insight:** Use `ShouldQueue` for cross-domain listeners to avoid transaction issues and tight coupling.

---

## Pattern 3: Re-enrollment / Reactivation

**Context:** User dropped enrollment should be able to re-enroll by reactivating existing record.

### The Problem

```sql
-- Unique constraint blocks re-enrollment
UNIQUE(user_id, learning_path_id)  -- Can't INSERT if dropped record exists
```

### Solution: Reactivate Existing Record

```php
public function enroll(User $user, LearningPath $path, bool $preserveProgress = false): PathEnrollmentResult
{
    $this->validateEnrollment($user, $path);

    // Check for existing dropped enrollment FIRST
    $droppedEnrollment = $this->getDroppedEnrollment($user, $path);

    if ($droppedEnrollment) {
        return $this->reactivateEnrollment($droppedEnrollment, $preserveProgress);
    }

    // Create new enrollment (first time)
    return $this->createNewEnrollment($user, $path);
}

protected function getDroppedEnrollment(User $user, LearningPath $path): ?LearningPathEnrollment
{
    return LearningPathEnrollment::where('user_id', $user->id)
        ->where('learning_path_id', $path->id)
        ->whereState('state', DroppedPathState::class)
        ->first();
}

protected function reactivateEnrollment(
    LearningPathEnrollment $enrollment,
    bool $preserveProgress
): PathEnrollmentResult {
    $enrollment->update([
        'state' => ActivePathState::$name,
        'dropped_at' => null,
        'drop_reason' => null,
    ]);

    if ($preserveProgress) {
        // Re-link existing course enrollments
        $this->relinkCourseEnrollments($enrollment);
        $message = 'Successfully re-enrolled with previous progress preserved.';
    } else {
        // Reset progress completely
        $enrollment->courseProgress()->delete();
        $this->initializeCourseProgress($enrollment);
        $enrollment->update(['progress_percentage' => 0]);
        $message = 'Successfully re-enrolled with fresh start.';
    }

    PathEnrollmentCreated::dispatch($enrollment);

    return new PathEnrollmentResult(
        enrollment: $enrollment->fresh(),
        isNewEnrollment: false,  // Signal: reactivation, not creation
        message: $message,
    );
}
```

### Update canEnroll to Allow Re-enrollment

```php
public function canEnroll(User $user, LearningPath $path): bool
{
    if (!$path->isPublished()) {
        return false;
    }

    // Check for active or completed enrollment (not dropped!)
    $existingEnrollment = LearningPathEnrollment::where('user_id', $user->id)
        ->where('learning_path_id', $path->id)
        ->whereNotState('state', DroppedPathState::class)  // Allow dropped
        ->exists();

    return !$existingEnrollment;
}
```

---

## Pattern 4: Required vs Optional Progress Calculation

**Context:** Completion based on required items only, but track both for UI display.

### The Problem

```php
// calculateProgressPercentage() counted ALL courses
// isPathCompleted() only checked REQUIRED courses
// UI showed 80% but path was "complete" → Confusing!
```

### Solution: Consistent Required-Only Calculation

```php
public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int
{
    // Count required courses only
    $totalRequired = $enrollment->courseProgress()
        ->whereHas('pathCourse', fn($q) => $q->where('is_required', true))
        ->count();

    // Backwards compatibility: if none marked required, all are required
    if ($totalRequired === 0) {
        $totalRequired = $enrollment->courseProgress()->count();
    }

    if ($totalRequired === 0) {
        return 0;
    }

    // Count completed required courses
    $completedRequired = $enrollment->courseProgress()
        ->where('state', CompletedCourseState::$name)
        ->whereHas('pathCourse', fn($q) => $q->where('is_required', true))
        ->count();

    return (int) round(($completedRequired / $totalRequired) * 100);
}
```

### DTO Exposes Both Metrics

```php
// PathProgressResult.php
public function __construct(
    // Overall stats (all courses)
    public readonly int $totalCourses,
    public readonly int $completedCourses,
    public readonly Percentage $overallPercentage,

    // Required-only stats (for completion logic)
    public readonly int $requiredCourses,
    public readonly int $completedRequiredCourses,
    public readonly ?int $requiredPercentage,  // This drives completion!

    public readonly bool $isCompleted,
    // ...
) {}
```

### Frontend Can Show Both

```vue
<template>
  <!-- Primary: Required progress -->
  <ProgressBar :value="progress.requiredPercentage" />
  <span>{{ progress.completedRequiredCourses }}/{{ progress.requiredCourses }} Wajib</span>

  <!-- Secondary: Overall including optional -->
  <span class="text-muted">
    {{ progress.completedCourses }}/{{ progress.totalCourses }} Total
  </span>
</template>
```

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `PathEnrollmentService.php` | Enrollment lifecycle, cascade enrollment, re-enrollment |
| `PathProgressService.php` | Progress calculation, course unlocking |
| `UpdatePathProgressOnCourseDrop.php` | Cross-domain sync listener |
| `PathEnrollmentResult.php` | DTO with `isNewEnrollment` and `message` |
| `PathProgressResult.php` | DTO with required/optional stats |
| `LearningPathCourseProgress.php` | Links path enrollment to course enrollment |

---

## State Machines

### LearningPathEnrollment States

```php
ActivePathState::$name    = 'active'     // Can progress
CompletedPathState::$name = 'completed'  // All required done
DroppedPathState::$name   = 'dropped'    // User quit

// Transitions
active → completed  (all required courses done)
active → dropped    (user drops)
completed → active  (course enrollment dropped, revert)
dropped → active    (re-enrollment)
```

### LearningPathCourseProgress States

```php
LockedCourseState::$name     = 'locked'      // Prerequisites not met
AvailableCourseState::$name  = 'available'   // Can start, has enrollment
InProgressCourseState::$name = 'in_progress' // Started
CompletedCourseState::$name  = 'completed'   // Done

// Transitions
locked → available     (prerequisites met, enrollment created)
available → in_progress (user starts course)
in_progress → completed (course finished)
completed → available   (course enrollment dropped, revert)
```

---

## Testing Patterns

### Test Cascade Enrollment

```php
it('creates course enrollment for first available course', function () {
    $user = User::factory()->create();
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $index => $course) {
        $path->courses()->attach($course->id, ['position' => $index + 1]);
    }

    $result = $this->service->enroll($user, $path);

    $progress = $result->enrollment->courseProgress()->orderBy('position')->get();

    // First course has enrollment linked
    expect($progress[0]->course_enrollment_id)->not->toBeNull();
    expect($progress[0]->isAvailable())->toBeTrue();

    // Others are locked without enrollment
    expect($progress[1]->course_enrollment_id)->toBeNull();
    expect($progress[1]->isLocked())->toBeTrue();
});
```

### Test Cross-Domain Sync

```php
it('reverts completed course progress when enrollment dropped', function () {
    // Setup: completed course in path
    $pathEnrollment = setupCompletedCourseInPath();
    $courseEnrollment = $pathEnrollment->courseProgress()->first()->courseEnrollment;

    // Drop the course enrollment
    $courseEnrollment->update(['status' => 'dropped']);

    // Trigger listener
    $listener = app(UpdatePathProgressOnCourseDrop::class);
    $listener->handle(new UserDropped($courseEnrollment, 'Testing'));

    // Verify course progress reverted
    $courseProgress = $pathEnrollment->courseProgress()->first()->fresh();
    expect($courseProgress->isAvailable())->toBeTrue();
    expect($courseProgress->completed_at)->toBeNull();

    // Verify path progress recalculated
    expect($pathEnrollment->fresh()->progress_percentage)->toBe(0);
});
```

### Test Re-enrollment

```php
it('reactivates dropped enrollment instead of creating new', function () {
    $user = User::factory()->create();
    $path = LearningPath::factory()->published()->create();

    // First enrollment, then drop
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
    ]);

    // Re-enroll
    $result = $this->service->enroll($user, $path);

    expect($result->isNewEnrollment)->toBeFalse();
    expect($result->enrollment->id)->toBe($droppedEnrollment->id);
    expect($result->enrollment->isActive())->toBeTrue();

    // Only one record exists
    $count = LearningPathEnrollment::where('user_id', $user->id)
        ->where('learning_path_id', $path->id)
        ->count();
    expect($count)->toBe(1);
});
```

---

## Common Gotchas

### 1. Forgetting to Link Enrollment

```php
// ❌ WRONG: Progress created without enrollment link
LearningPathCourseProgress::create([
    'state' => AvailableCourseState::$name,
    // Missing course_enrollment_id!
]);

// ✅ CORRECT: Always link for non-locked states
$courseEnrollment = $this->ensureCourseEnrollment($user, $course);
LearningPathCourseProgress::create([
    'state' => AvailableCourseState::$name,
    'course_enrollment_id' => $courseEnrollment->id,
]);
```

### 2. Not Handling Existing Enrollments

```php
// ❌ WRONG: Creates duplicate enrollment
$this->enrollmentService->enroll($user, $course);

// ✅ CORRECT: Check first, reuse if exists
$existing = $this->enrollmentService->getActiveEnrollment($user, $course);
$enrollment = $existing ?? $this->enrollmentService->enroll($user, $course)->enrollment;
```

### 3. Progress Mismatch

```php
// ❌ WRONG: Different logic in calculation vs completion
calculateProgressPercentage()  // counts all courses
isPathCompleted()              // checks required only

// ✅ CORRECT: Same logic everywhere
// Both use required courses (with fallback to all if none marked required)
```

---

## Related Skills

- `enteraksi-models` - Spatie states, model relationships
- `enteraksi-testing` - Testing patterns
- `laravel-learnings` - General Laravel patterns
