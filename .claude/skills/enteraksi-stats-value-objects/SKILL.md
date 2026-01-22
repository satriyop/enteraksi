---
name: enteraksi-stats-value-objects
description: Value objects for statistics and computed data in Enteraksi LMS. Use when creating stats DTOs, progress results, or any computed data that needs helper methods and API serialization.
triggers:
  - stats
  - statistics
  - value object
  - computed data
  - toResponse
  - API response
  - progress stats
  - assessment stats
  - helper methods
  - fromArray
---

# Enteraksi Stats Value Objects Pattern

## When to Use This Skill

- Creating objects to hold computed statistics (counts, percentages, aggregates)
- Building data that needs helper methods (e.g., `allRequiredPassed()`)
- Preparing data for API/Inertia responses with `toResponse()`
- Encapsulating related stats that travel together

## The Pattern

Stats value objects:
1. Are `final readonly class` (immutable, no inheritance)
2. Have a `static fromArray()` factory method
3. Have a `toResponse()` method for API serialization
4. Include helper methods for common calculations

## Real Example: AssessmentStats

```php
// app/Domain/Progress/ValueObjects/AssessmentStats.php
namespace App\Domain\Progress\ValueObjects;

final readonly class AssessmentStats
{
    public function __construct(
        public int $total,
        public int $passed,
        public int $pending,
        public int $requiredTotal,
        public int $requiredPassed,
    ) {}

    /**
     * Create empty stats (when no assessments exist).
     */
    public static function empty(): self
    {
        return new self(
            total: 0,
            passed: 0,
            pending: 0,
            requiredTotal: 0,
            requiredPassed: 0,
        );
    }

    /**
     * Create from array (snake_case from service/calculator).
     */
    public static function fromArray(array $stats): self
    {
        return new self(
            total: $stats['total'] ?? 0,
            passed: $stats['passed'] ?? 0,
            pending: $stats['pending'] ?? 0,
            requiredTotal: $stats['required_total'] ?? 0,
            requiredPassed: $stats['required_passed'] ?? 0,
        );
    }

    /**
     * Check if all required assessments have been passed.
     */
    public function allRequiredPassed(): bool
    {
        return $this->requiredTotal === 0
            || $this->requiredPassed >= $this->requiredTotal;
    }

    /**
     * Get count of required assessments still pending.
     */
    public function requiredPending(): int
    {
        return max(0, $this->requiredTotal - $this->requiredPassed);
    }

    /**
     * Serialize for API/Inertia response (snake_case).
     */
    public function toResponse(): array
    {
        return [
            'total' => $this->total,
            'passed' => $this->passed,
            'pending' => $this->pending,
            'required_total' => $this->requiredTotal,
            'required_passed' => $this->requiredPassed,
            'required_pending' => $this->requiredPending(),
            'all_required_passed' => $this->allRequiredPassed(),
        ];
    }
}
```

## Using in Services

```php
// app/Domain/Progress/Services/ProgressTrackingService.php

public function getAssessmentStats(Enrollment $enrollment): AssessmentStats
{
    // Delegate to calculator if it supports assessment stats
    if ($this->calculator instanceof AssessmentInclusiveProgressCalculator) {
        return AssessmentStats::fromArray(
            $this->calculator->getAssessmentStats($enrollment)
        );
    }

    // Return empty stats if calculator doesn't support assessments
    return AssessmentStats::empty();
}
```

## Using in Controllers

```php
// app/Http/Controllers/CourseController.php

public function show(Request $request, Course $course): Response
{
    $enrollment = $user->enrollments()->where('course_id', $course->id)->first();

    // Get stats and convert to response format
    $assessmentStats = null;
    if ($enrollment) {
        $assessmentStats = $this->progressService
            ->getAssessmentStats($enrollment)
            ->toResponse();
    }

    return Inertia::render('courses/Detail', [
        'course' => $course,
        'enrollment' => $enrollment,
        'assessmentStats' => $assessmentStats,  // Ready for frontend
    ]);
}
```

## Frontend TypeScript Interface

Match the `toResponse()` structure:

```typescript
// resources/js/types/index.ts or inline in component

interface AssessmentStats {
    total: number;
    passed: number;
    pending: number;
    required_total: number;
    required_passed: number;
    required_pending: number;
    all_required_passed: boolean;
}

interface Props {
    assessmentStats: AssessmentStats | null;
}
```

## Helper Methods to Include

Common helper patterns:

```php
final readonly class AssessmentStats
{
    // Completion check
    public function allRequiredPassed(): bool
    {
        return $this->requiredTotal === 0
            || $this->requiredPassed >= $this->requiredTotal;
    }

    // Computed property
    public function requiredPending(): int
    {
        return max(0, $this->requiredTotal - $this->requiredPassed);
    }

    // Percentage calculation
    public function completionPercentage(): float
    {
        if ($this->requiredTotal === 0) {
            return 100.0;
        }
        return round(($this->requiredPassed / $this->requiredTotal) * 100, 2);
    }

    // Boolean check for UI
    public function hasPendingRequired(): bool
    {
        return $this->requiredPending() > 0;
    }

    // Empty check
    public function isEmpty(): bool
    {
        return $this->total === 0;
    }
}
```

## Contract Integration

Add to service contract:

```php
// app/Domain/Progress/Contracts/ProgressTrackingServiceContract.php

use App\Domain\Progress\ValueObjects\AssessmentStats;

interface ProgressTrackingServiceContract
{
    // ... other methods

    /**
     * Get assessment completion statistics for progress visibility.
     */
    public function getAssessmentStats(Enrollment $enrollment): AssessmentStats;
}
```

## Including Stats in Result DTOs

For operations that return both result and stats:

```php
// app/Domain/Progress/DTOs/ProgressResult.php

final readonly class ProgressResult
{
    public function __construct(
        public LessonProgress $progress,
        public Percentage $coursePercentage,
        public bool $lessonCompleted,
        public bool $courseCompleted,
        public ?AssessmentStats $assessmentStats = null,  // Optional stats
    ) {}
}
```

## Naming Conventions

| Pattern | Example |
|---------|---------|
| Stats value object | `AssessmentStats`, `ProgressStats`, `EnrollmentStats` |
| Empty factory | `::empty()` |
| From array factory | `::fromArray(array $data)` |
| Response serializer | `->toResponse()` |
| Boolean helpers | `allRequiredPassed()`, `hasPending()`, `isEmpty()` |
| Computed helpers | `requiredPending()`, `completionPercentage()` |

## Testing Stats Value Objects

```php
it('calculates all_required_passed correctly', function () {
    $stats = new AssessmentStats(
        total: 5,
        passed: 3,
        pending: 2,
        requiredTotal: 2,
        requiredPassed: 2,
    );

    expect($stats->allRequiredPassed())->toBeTrue();
    expect($stats->requiredPending())->toBe(0);
});

it('returns correct required_pending when some required not passed', function () {
    $stats = new AssessmentStats(
        total: 5,
        passed: 1,
        pending: 4,
        requiredTotal: 3,
        requiredPassed: 1,
    );

    expect($stats->allRequiredPassed())->toBeFalse();
    expect($stats->requiredPending())->toBe(2);
});

it('handles empty assessments', function () {
    $stats = AssessmentStats::empty();

    expect($stats->total)->toBe(0);
    expect($stats->allRequiredPassed())->toBeTrue();  // No required = all passed
});
```

## Files to Reference

```
app/Domain/Progress/ValueObjects/AssessmentStats.php       # Stats value object
app/Domain/Shared/ValueObjects/Percentage.php              # Value object with validation
app/Domain/Progress/DTOs/ProgressResult.php                # DTO with optional stats
app/Domain/Progress/Contracts/ProgressTrackingServiceContract.php  # Contract with stats method
```
