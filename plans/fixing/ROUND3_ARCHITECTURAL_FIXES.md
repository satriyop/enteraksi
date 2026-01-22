# Round 3: Architectural & Security Fixes

> Prioritized fixes for architectural violations and security issues identified in code review Round 3.

## Status: ✅ COMPLETED

| Priority | Issue | Status | Risk Level |
|----------|-------|--------|------------|
| 🔴 P0 | AssessmentAttemptPolicy authorization holes | ✅ Fixed | SECURITY |
| 🔴 P1 | DTOs holding Eloquent models | ✅ Fixed | DATA LEAK |
| 🟠 P2 | Service contract signature mismatch | ✅ Fixed | RUNTIME BUG |
| 🟡 P3 | LearningPathEnrollment N+1 accessor | ✅ Fixed | PERFORMANCE |

### Completed: 2026-01-21

All fixes verified with **1362 passing tests**.

---

## 🔴 P0: AssessmentAttemptPolicy Authorization Holes

### Problem

`viewAny()` and `create()` return `true` unconditionally, allowing any authenticated user to:
- List ALL assessment attempts in the system (privacy breach)
- Create attempts for courses they're not enrolled in (logic bypass)

### File

`app/Policies/AssessmentAttemptPolicy.php`

### Current Code (BAD)

```php
public function viewAny(User $user): bool
{
    return true;  // Anyone can list all attempts!
}

public function create(User $user): bool
{
    return true;  // Anyone can create attempts!
}
```

### Proposed Fix

```php
/**
 * Determine whether the user can view any assessment attempts.
 *
 * Admins and content managers can view all attempts for grading/reporting.
 * Learners can only view their own attempts (handled by query scoping).
 */
public function viewAny(User $user): bool
{
    return $user->isLmsAdmin() || $user->isContentManager();
}

/**
 * Determine whether the user can create an assessment attempt.
 *
 * Note: The actual enrollment/attempt-limit checks happen in Assessment::canBeAttemptedBy()
 * This policy gate is a first-pass authorization check.
 */
public function create(User $user, Assessment $assessment): bool
{
    // Learners can attempt published assessments if enrolled
    if ($user->isLearner()) {
        return $assessment->status === 'published' &&
               $user->enrollments()
                   ->where('course_id', $assessment->course_id)
                   ->whereIn('status', ['active', 'in_progress'])
                   ->exists();
    }

    // Admins/content managers cannot "attempt" assessments
    return false;
}
```

### Controller Updates Needed

`AssessmentController::startAttempt()` needs to pass the assessment to the policy:

```php
// Before
Gate::authorize('create', AssessmentAttempt::class);

// After
Gate::authorize('create', [AssessmentAttempt::class, $assessment]);
```

### Test Cases to Add

```php
it('prevents unenrolled users from creating attempts', function () {
    $user = User::factory()->learner()->create();
    $assessment = Assessment::factory()->published()->create();

    expect($user->can('create', [AssessmentAttempt::class, $assessment]))->toBeFalse();
});

it('allows enrolled learners to create attempts', function () {
    $user = User::factory()->learner()->create();
    $course = Course::factory()->published()->create();
    $assessment = Assessment::factory()->for($course)->published()->create();
    Enrollment::factory()->for($user)->for($course)->active()->create();

    expect($user->can('create', [AssessmentAttempt::class, $assessment]))->toBeTrue();
});

it('prevents learners from viewing all attempts', function () {
    $learner = User::factory()->learner()->create();

    expect($learner->can('viewAny', AssessmentAttempt::class))->toBeFalse();
});

it('allows admins to view all attempts', function () {
    $admin = User::factory()->lmsAdmin()->create();

    expect($admin->can('viewAny', AssessmentAttempt::class))->toBeTrue();
});
```

---

## 🔴 P1: DTOs Holding Eloquent Models

### Problem

Several DTOs hold Eloquent model instances instead of primitives:
- `ProgressResult` holds `LessonProgress` model
- `EnrollmentResult` holds `Enrollment` model

This causes:
- Potential data leakage via `toArray()`
- Serialization issues for queued jobs
- Tight coupling between DTO and model structure

### Files

1. `app/Domain/Progress/DTOs/ProgressResult.php`
2. `app/Domain/Enrollment/DTOs/EnrollmentResult.php`

### Current Code (BAD)

```php
// ProgressResult.php
public function __construct(
    public LessonProgress $progress,  // Eloquent model!
    public Percentage $coursePercentage,
    public bool $lessonCompleted,
    public bool $courseCompleted,
    public ?AssessmentStats $assessmentStats = null,
) {}

public function toResponse(): array
{
    return [
        'progress' => $this->progress->toArray(),  // Leaks all attributes!
        // ...
    ];
}
```

### Proposed Fix - ProgressResult

```php
final class ProgressResult extends DataTransferObject
{
    public function __construct(
        public readonly int $progressId,
        public readonly int $enrollmentId,
        public readonly int $lessonId,
        public readonly bool $isCompleted,
        public readonly int $progressPercentage,
        public readonly ?int $timeSpentSeconds,
        public readonly Percentage $coursePercentage,
        public readonly bool $lessonCompleted,
        public readonly bool $courseCompleted,
        public readonly ?AssessmentStats $assessmentStats = null,
    ) {}

    /**
     * Create from LessonProgress model (extraction point).
     */
    public static function fromProgress(
        LessonProgress $progress,
        Percentage $coursePercentage,
        bool $lessonCompleted,
        bool $courseCompleted,
        ?AssessmentStats $assessmentStats = null
    ): self {
        return new self(
            progressId: $progress->id,
            enrollmentId: $progress->enrollment_id,
            lessonId: $progress->lesson_id,
            isCompleted: $progress->is_completed,
            progressPercentage: $progress->progress_percentage ?? 0,
            timeSpentSeconds: $progress->time_spent_seconds,
            coursePercentage: $coursePercentage,
            lessonCompleted: $lessonCompleted,
            courseCompleted: $courseCompleted,
            assessmentStats: $assessmentStats,
        );
    }

    public function toResponse(): array
    {
        return [
            'progress' => [
                'id' => $this->progressId,
                'enrollment_id' => $this->enrollmentId,
                'lesson_id' => $this->lessonId,
                'is_completed' => $this->isCompleted,
                'progress_percentage' => $this->progressPercentage,
                'time_spent_seconds' => $this->timeSpentSeconds,
            ],
            'course_percentage' => $this->coursePercentage->value,
            'lesson_completed' => $this->lessonCompleted,
            'course_completed' => $this->courseCompleted,
            'assessment_stats' => $this->assessmentStats?->toResponse(),
        ];
    }
}
```

### Service Update Required

`ProgressTrackingService::updateProgress()` needs to use the factory method:

```php
// Before
return new ProgressResult(
    progress: $progress,
    coursePercentage: new Percentage($enrollment->progress_percentage),
    // ...
);

// After
return ProgressResult::fromProgress(
    progress: $progress,
    coursePercentage: new Percentage($enrollment->progress_percentage),
    lessonCompleted: $justCompleted,
    courseCompleted: $enrollment->status === 'completed',
    assessmentStats: $assessmentStats,
);
```

### Proposed Fix - EnrollmentResult

```php
final class EnrollmentResult extends DataTransferObject
{
    public function __construct(
        public readonly int $enrollmentId,
        public readonly int $userId,
        public readonly int $courseId,
        public readonly string $status,
        public readonly bool $isNewEnrollment,
        public readonly ?string $enrolledAt = null,
    ) {}

    public static function fromEnrollment(Enrollment $enrollment, bool $isNewEnrollment): self
    {
        return new self(
            enrollmentId: $enrollment->id,
            userId: $enrollment->user_id,
            courseId: $enrollment->course_id,
            status: (string) $enrollment->status,
            isNewEnrollment: $isNewEnrollment,
            enrolledAt: $enrollment->enrolled_at?->toIso8601String(),
        );
    }

    /**
     * Get the enrollment model if needed for further operations.
     * Note: This triggers a DB query - use sparingly.
     */
    public function getEnrollment(): Enrollment
    {
        return Enrollment::findOrFail($this->enrollmentId);
    }
}
```

---

## 🟠 P2: Service Contract Signature Mismatch

### Problem

`EnrollmentService` and `PathEnrollmentService` both have `reactivateEnrollment()` methods with incompatible signatures:

```php
// EnrollmentService
reactivateEnrollment(Enrollment $enrollment, CreateEnrollmentDTO $dto, bool $preserveProgress)

// PathEnrollmentService
reactivateEnrollment(LearningPathEnrollment $enrollment, bool $preserveProgress)
```

This violates the principle of consistent interfaces and makes polymorphic usage impossible.

### Files

1. `app/Domain/Enrollment/Services/EnrollmentService.php`
2. `app/Domain/LearningPath/Services/PathEnrollmentService.php`
3. `app/Domain/Enrollment/Contracts/EnrollmentServiceContract.php`
4. `app/Domain/LearningPath/Contracts/PathEnrollmentServiceContract.php`

### Proposed Fix

**Option A: Rename to avoid confusion (Recommended)**

Since these are different domains, rename to make the distinction clear:

```php
// EnrollmentService - course enrollments
public function reactivateCourseEnrollment(
    Enrollment $enrollment,
    CreateEnrollmentDTO $dto,
    bool $preserveProgress = true
): EnrollmentResult;

// PathEnrollmentService - learning path enrollments
public function reactivatePathEnrollment(
    LearningPathEnrollment $enrollment,
    bool $preserveProgress = false
): PathEnrollmentResult;
```

**Option B: Align signatures with optional DTO**

```php
// Both services
public function reactivateEnrollment(
    Enrollment|LearningPathEnrollment $enrollment,
    bool $preserveProgress = true,
    ?CreateEnrollmentDTO $dto = null  // Optional, only used by course enrollment
): EnrollmentResult|PathEnrollmentResult;
```

**Recommendation:** Option A is cleaner - these are different bounded contexts and shouldn't share method names.

### Contract Updates

```php
// EnrollmentServiceContract.php
public function reactivateCourseEnrollment(
    Enrollment $enrollment,
    CreateEnrollmentDTO $dto,
    bool $preserveProgress = true
): EnrollmentResult;

// PathEnrollmentServiceContract.php
public function reactivatePathEnrollment(
    LearningPathEnrollment $enrollment,
    bool $preserveProgress = false
): PathEnrollmentResult;
```

### Controller Updates

```php
// EnrollmentController::reenroll()
$result = $this->enrollmentService->reactivateCourseEnrollment(
    $droppedEnrollment,
    $dto,
    $preserveProgress
);
```

---

## 🟡 P3: LearningPathEnrollment N+1 Accessor

### Problem

`getCompletedCoursesCountAttribute()` queries the database on every access:

```php
public function getCompletedCoursesCountAttribute(): int
{
    return $this->courseProgress()
        ->where('state', 'completed')
        ->count();  // Query every time!
}
```

### File

`app/Models/LearningPathEnrollment.php:128-131`

### Proposed Fix

Apply same pattern as Course model:

```php
/**
 * Get completed courses count.
 * Uses pre-loaded count if available (via withCount()),
 * otherwise falls back to query.
 */
public function getCompletedCoursesCountAttribute(): int
{
    // Check for pre-loaded count first
    // Note: Laravel stores this as course_progress_count with a filter suffix
    $preloadedKey = 'course_progress_count';  // or custom alias

    if (array_key_exists($preloadedKey, $this->attributes)) {
        return (int) $this->attributes[$preloadedKey];
    }

    // Fallback to query
    return $this->courseProgress()
        ->where('state', 'completed')
        ->count();
}
```

**Better approach:** Use a scoped count in queries:

```php
// In controller/service
$enrollments = LearningPathEnrollment::query()
    ->withCount(['courseProgress as completed_courses_count' => function ($query) {
        $query->where('state', 'completed');
    }])
    ->get();

// Now $enrollment->completed_courses_count uses pre-loaded value
```

---

## Implementation Order

1. **P0: AssessmentAttemptPolicy** (~15 min)
   - Fix policy methods
   - Update controller Gate calls
   - Add tests

2. **P1: DTO Refactoring** (~45 min)
   - Update ProgressResult with factory method
   - Update EnrollmentResult with factory method
   - Update all service call sites
   - Run tests to verify

3. **P2: Service Signatures** (~20 min)
   - Rename methods in both services
   - Update contracts
   - Update all call sites
   - Run tests

4. **P3: Accessor Fix** (~10 min)
   - Update accessor with pre-load check
   - Update queries that use this attribute

---

## Verification

```bash
# Run all related tests
php artisan test --filter="Policy|Enrollment|Progress|LearningPath" --parallel

# Check for any remaining policy issues
grep -r "return true" app/Policies/ --include="*.php"
```
