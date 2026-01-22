---
name: enteraksi-eloquent-gotchas
description: Eloquent anti-patterns and gotchas discovered in Enteraksi. Use when working with transactions, model refreshing, service layer patterns, or debugging unnecessary database queries.
triggers:
  - fresh()
  - refresh()
  - transaction
  - DB::transaction
  - unnecessary query
  - model not updated
  - stale data
  - duplicate code service
  - DRY service
  - service refactoring
---

# Enteraksi Eloquent Gotchas

## When to Use This Skill

- Working with database transactions
- Deciding between `fresh()` vs `refresh()`
- Debugging "model not updating" issues
- Refactoring duplicate service logic
- Optimizing query counts in services

---

## fresh() vs refresh() — The Critical Difference

### The Anti-Pattern: Excessive fresh() in Transactions

```php
// ❌ BAD: 5 unnecessary queries inside transaction
return DB::transaction(function () use ($dto, $enrollment, $progress) {
    $progress->save();
    $enrollment->update(['last_lesson_id' => $dto->lessonId]);

    // Each fresh() = 1 database query!
    ProgressUpdated::dispatch($enrollment->fresh(), $progress->fresh());

    return new ProgressResult(
        progress: $progress->fresh(),           // +1 query
        coursePercentage: $enrollment->fresh()->progress_percentage,  // +1 query
        courseCompleted: $enrollment->fresh()->status === 'completed', // +1 query
    );
});
```

### The Fix: Understand When Data Is Already Fresh

**After `save()` or `update()`:**
- The model instance **already has** the updated values
- No query needed to access them

**After external changes (another method updates the model):**
- Use `refresh()` **once** to reload
- `refresh()` mutates the same instance (1 query)
- `fresh()` returns a new instance (1 query per call)

```php
// ✅ GOOD: 1 query only when needed
return DB::transaction(function () use ($dto, $enrollment, $progress) {
    $progress->save();
    $enrollment->update(['last_lesson_id' => $dto->lessonId]);

    if ($justCompleted) {
        // This method may update enrollment externally
        $this->handleLessonCompletion($enrollment, $lesson, $progress);
    }

    // Refresh ONCE after external updates
    $enrollment->refresh();

    // No more fresh() calls - use the models directly
    ProgressUpdated::dispatch($enrollment, $progress);

    return new ProgressResult(
        progress: $progress,
        coursePercentage: new Percentage($enrollment->progress_percentage),
        courseCompleted: $enrollment->status === 'completed',
    );
});
```

### When to Use Each

| Scenario | Use | Why |
|----------|-----|-----|
| After `save()`/`update()` | Nothing | Model already updated |
| After external method updates model | `$model->refresh()` | Reload in-place (1 query) |
| Need separate copy of current DB state | `$model->fresh()` | Returns new instance |
| Need to reload with specific relations | `$model->fresh(['relation'])` | Fresh with eager load |

### Real Examples from Enteraksi

**ProgressTrackingService — Before:**
```php
// 5 queries for data we already have!
return new ProgressResult(
    progress: $progress->fresh(),
    coursePercentage: new Percentage($enrollment->fresh()->progress_percentage),
    courseCompleted: $enrollment->fresh()->status === 'completed',
);
```

**ProgressTrackingService — After:**
```php
// 1 query (refresh after handleLessonCompletion)
$enrollment->refresh();

return new ProgressResult(
    progress: $progress,
    coursePercentage: new Percentage($enrollment->progress_percentage),
    courseCompleted: $enrollment->status === 'completed',
);
```

---

## DRY Violation in Service Methods

### The Problem: Duplicate Query Logic

```php
// ❌ BAD: Same logic duplicated in PathProgressService
public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int
{
    $totalRequired = $enrollment->courseProgress()
        ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
        ->count();

    if ($totalRequired === 0) {
        $totalRequired = $enrollment->courseProgress()->count();
    }

    $completedRequired = $enrollment->courseProgress()
        ->where('state', CompletedCourseState::$name)
        ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
        ->count();

    return (int) round(($completedRequired / $totalRequired) * 100);
}

public function isPathCompleted(LearningPathEnrollment $enrollment): bool
{
    // SAME 20 lines of query logic repeated!
    $totalRequired = $enrollment->courseProgress()
        ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
        ->count();
    // ... identical logic ...
}
```

### The Fix: Extract Shared Stats Method

```php
// ✅ GOOD: Single source of truth
/**
 * @return array{total: int, completed: int}
 */
protected function getRequiredCourseStats(LearningPathEnrollment $enrollment): array
{
    $hasExplicitRequired = $enrollment->courseProgress()
        ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
        ->exists();

    if ($hasExplicitRequired) {
        $total = $enrollment->courseProgress()
            ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
            ->count();

        $completed = $enrollment->courseProgress()
            ->where('state', CompletedCourseState::$name)
            ->whereHas('pathCourse', fn ($q) => $q->where('is_required', true))
            ->count();
    } else {
        $total = $enrollment->courseProgress()->count();
        $completed = $enrollment->courseProgress()
            ->where('state', CompletedCourseState::$name)
            ->count();
    }

    return ['total' => $total, 'completed' => $completed];
}

public function calculateProgressPercentage(LearningPathEnrollment $enrollment): int
{
    $stats = $this->getRequiredCourseStats($enrollment);
    return $stats['total'] === 0 ? 0 : (int) round(($stats['completed'] / $stats['total']) * 100);
}

public function isPathCompleted(LearningPathEnrollment $enrollment): bool
{
    $stats = $this->getRequiredCourseStats($enrollment);
    return $stats['total'] === 0 || $stats['completed'] >= $stats['total'];
}
```

### Benefits
- **Single source of truth** — Change logic once
- **Easier testing** — Test the extracted method
- **Potential optimization** — Could cache stats within request

---

## Common Gotchas Quick Reference

### 1. update() Already Syncs Model
```php
$model->update(['status' => 'active']);
// $model->status is NOW 'active' — no fresh() needed!
```

### 2. create() Returns Hydrated Model
```php
$enrollment = Enrollment::create([...]);
// $enrollment is fully populated — no fresh() needed!
```

### 3. Relationship Changes Need Reload
```php
$course->tags()->sync($tagIds);
// $course->tags is STALE — need to reload
$course->load('tags');  // or $course->refresh()
```

### 4. Events Get Stale Models If Passed Before Update
```php
// ❌ BAD: Event gets model before update
SomeEvent::dispatch($model);
$model->update([...]);

// ✅ GOOD: Update then dispatch
$model->update([...]);
SomeEvent::dispatch($model);  // Now has updated data
```

### 5. Vacuous Truth Edge Case
```php
// When total is 0, be explicit about what "complete" means
public function isComplete(): bool
{
    $stats = $this->getStats();

    // Zero items = vacuously complete (not incomplete!)
    if ($stats['total'] === 0) {
        return true;
    }

    return $stats['completed'] >= $stats['total'];
}
```

---

## Query Count Savings Reference

| Operation | Before Fix | After Fix | Savings |
|-----------|------------|-----------|---------|
| Progress update | 6 queries | 2 queries | -67% |
| Path completion check | 4 queries | 2 queries | -50% |
| Re-enrollment | 2 queries | 1 query | -50% |

### 6. Hidden Queries in Policies

```php
// ❌ BAD: Policies run frequently, queries are hidden
public function enroll(User $user, Course $course): bool
{
    // Query #1 - every authorization check!
    if ($user->enrollments()->where('course_id', $course->id)->exists()) {
        return false;
    }

    // Query #2 - for restricted courses
    return $user->courseInvitations()
        ->where('course_id', $course->id)
        ->exists();
}
```

**Why it's bad:**
1. Policies run on every request to protected routes
2. Queries hidden from controller-level optimization
3. Can't eager load for batch authorization
4. Violates separation of concerns

```php
// ✅ GOOD: Controller pre-fetches, policy uses pure logic
// See enteraksi-architecture skill for EnrollmentContext DTO pattern

// Controller:
$context = EnrollmentContext::for($user, $course);
Gate::authorize('enroll', [$course, $context]);

// Policy (no queries):
public function enroll(User $user, Course $course, EnrollmentContext $context): bool
{
    if ($context->isActivelyEnrolled) return false;
    return $course->visibility === 'public' || $context->hasPendingInvitation;
}
```

---

## Files to Reference

```
app/Domain/Progress/Services/ProgressTrackingService.php      # fresh() removal example
app/Domain/Enrollment/Services/EnrollmentService.php          # Transaction patterns
app/Domain/Enrollment/DTOs/EnrollmentContext.php              # Policy context DTO
app/Policies/CoursePolicy.php                                 # Policy with context pattern
app/Domain/LearningPath/Services/PathProgressService.php      # DRY extraction example
app/Domain/LearningPath/Services/PathEnrollmentService.php    # Re-enrollment patterns
plans/fixing/CODE_QUALITY_REFACTORING.md                      # Full refactoring documentation
```
