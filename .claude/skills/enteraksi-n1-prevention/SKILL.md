---
name: enteraksi-n1-prevention
description: N+1 query prevention patterns for Enteraksi LMS. Use when optimizing loops, replacing in-loop queries, or debugging slow list/dashboard pages.
triggers:
  - N+1
  - slow query
  - query loop
  - optimize list
  - slow dashboard
  - prevent N+1
  - eager loading
  - query optimization
  - collection filtering
---

# Enteraksi N+1 Prevention Pattern

## When to Use This Skill

- Debugging slow list pages or dashboards
- Replacing queries inside foreach loops
- Optimizing progress calculations
- Working with related data for collections

## Identifying N+1 Problems

**Signs of N+1:**
```php
// Each iteration triggers a new database query
foreach ($enrollments as $enrollment) {
    // QUERY per enrollment - N+1!
    $attempts = AssessmentAttempt::where('enrollment_id', $enrollment->id)
        ->where('passed', true)
        ->exists();
}
```

**Use Laravel Telescope or Debugbar** to see query counts.

## The Fix: Collection Filtering

**Replace in-loop queries with collection methods:**

```php
// Step 1: Batch load ALL data upfront (1 query)
$passedAttempts = AssessmentAttempt::query()
    ->whereIn('enrollment_id', $enrollments->pluck('id'))
    ->where('passed', true)
    ->get();

// Step 2: Filter using collection methods (0 queries)
foreach ($enrollments as $enrollment) {
    $hasPassed = $passedAttempts
        ->where('enrollment_id', $enrollment->id)
        ->isNotEmpty();
}
```

## Real Example: Assessment Progress Calculator

**Before (N+1):**
```php
public function calculateAssessmentProgress(Enrollment $enrollment): float
{
    $assessments = $enrollment->course->assessments;
    $passedCount = 0;

    foreach ($assessments as $assessment) {
        // N+1: One query per assessment!
        if ($this->hasPassedAttempt($enrollment->user_id, $assessment->id)) {
            $passedCount++;
        }
    }

    return $assessments->count() > 0
        ? ($passedCount / $assessments->count()) * 100
        : 0;
}

private function hasPassedAttempt(int $userId, int $assessmentId): bool
{
    // This runs N times!
    return AssessmentAttempt::where('user_id', $userId)
        ->where('assessment_id', $assessmentId)
        ->where('passed', true)
        ->exists();
}
```

**After (Fixed):**
```php
public function calculateAssessmentProgress(Enrollment $enrollment): float
{
    $assessments = $enrollment->course->assessments()
        ->where('is_required', true)
        ->pluck('id');

    if ($assessments->isEmpty()) {
        return 100.0;
    }

    // ONE query for all passed assessments
    $passedIds = $this->getPassedAssessmentIds(
        $enrollment->user_id,
        $assessments
    );

    // Collection filtering - no queries
    $passedCount = $assessments->intersect($passedIds)->count();

    return ($passedCount / $assessments->count()) * 100;
}

/**
 * Single batch query for all passed assessments.
 */
protected function getPassedAssessmentIds(int $userId, Collection $assessmentIds): Collection
{
    if ($assessmentIds->isEmpty()) {
        return collect();
    }

    return AssessmentAttempt::query()
        ->where('user_id', $userId)
        ->whereIn('assessment_id', $assessmentIds)
        ->where('passed', true)
        ->distinct()
        ->pluck('assessment_id');
}
```

## Collection Filtering Methods

Replace SQL conditions with collection methods:

```php
// SQL: WHERE status = 'active'
$activeItems = $items->where('status', 'active');

// SQL: WHERE id IN (1, 2, 3)
$matchingItems = $items->whereIn('id', [1, 2, 3]);

// SQL: WHERE id NOT IN (1, 2, 3)
$excludedItems = $items->whereNotIn('id', [1, 2, 3]);

// SQL: WHERE deleted_at IS NULL
$nonDeletedItems = $items->whereNull('deleted_at');

// SQL: WHERE is_required = true
$requiredItems = $items->where('is_required', true);

// SQL: COUNT(*) WHERE passed = true
$passedCount = $items->where('passed', true)->count();

// SQL: SUM(score)
$totalScore = $items->sum('score');

// Check if any match
$hasActive = $items->where('status', 'active')->isNotEmpty();
```

## Eager Loading vs Batch Loading

**Use Eager Loading when:**
- Loading direct relationships
- Relationship is always needed
- Simple parent-child structure

```php
// Eager loading - good for simple relationships
$courses = Course::with(['sections.lessons', 'user'])->get();
```

**Use Batch Loading when:**
- Loading data for calculations
- Need conditional filtering after load
- Working with indirect relationships
- Aggregating stats

```php
// Batch loading - good for calculations
$lessonIds = $sections->flatMap->lessons->pluck('id');
$progressMap = LessonProgress::whereIn('lesson_id', $lessonIds)
    ->get()
    ->groupBy('lesson_id');
```

## Soft Delete Gotcha

When batch loading, remember to handle soft deletes:

```php
// WRONG: May include deleted records
$lessons = Lesson::whereIn('id', $lessonIds)->get();

// RIGHT: Explicitly exclude soft-deleted (or use default scope)
$lessons = Lesson::whereIn('id', $lessonIds)
    ->whereNull('deleted_at')  // If not using SoftDeletes trait
    ->get();

// Or rely on model's soft delete scope
$lessons = Lesson::whereIn('id', $lessonIds)->get(); // Auto-excludes if trait used
```

## Request-Level Caching

For repeated calculations within a request:

```php
class AssessmentInclusiveProgressCalculator
{
    private array $cache = [];

    protected function getCached(string $key, callable $callback): mixed
    {
        return $this->cache[$key] ??= $callback();
    }

    public function getPassedAssessmentIds(int $userId, Collection $assessmentIds): Collection
    {
        $cacheKey = "passed_assessments_{$userId}_" . $assessmentIds->implode('_');

        return $this->getCached($cacheKey, fn () =>
            AssessmentAttempt::query()
                ->where('user_id', $userId)
                ->whereIn('assessment_id', $assessmentIds)
                ->where('passed', true)
                ->distinct()
                ->pluck('assessment_id')
        );
    }
}
```

## Quick Checklist

Before writing a loop with queries:

- [ ] Can I batch load all data first?
- [ ] Can I use `whereIn` instead of individual queries?
- [ ] Can I use collection filtering instead of SQL conditions?
- [ ] Is this calculation repeated? Should I cache it?
- [ ] Am I handling soft deletes correctly?

## Accessor N+1 Trap (Hidden Queries)

Model accessors can silently execute queries on every access:

**The Problem:**
```php
// Course.php - HIDDEN N+1!
public function getTotalLessonsAttribute(): int
{
    return $this->lessons()->count();  // Query every time!
}

public function getAverageRatingAttribute(): ?float
{
    return $this->ratings()->avg('rating');  // Query every time!
}

// In a listing with 12 courses = 24 hidden queries!
foreach ($courses as $course) {
    echo $course->total_lessons;    // Query!
    echo $course->average_rating;   // Query!
}
```

**The Fix: Pre-loaded Counts with Fallback**

Step 1: Update accessors to check for pre-loaded values:
```php
// Course.php
public function getTotalLessonsAttribute(): int
{
    // Use pre-loaded count if available (via withCount('lessons'))
    if (array_key_exists('lessons_count', $this->attributes)) {
        return (int) $this->attributes['lessons_count'];
    }
    // Fallback - consider logging this as warning in dev
    return $this->lessons()->count();
}

public function getAverageRatingAttribute(): ?float
{
    // Use pre-loaded average if available (via withAvg('ratings', 'rating'))
    if (array_key_exists('ratings_avg_rating', $this->attributes)) {
        $avg = $this->attributes['ratings_avg_rating'];
        return $avg !== null ? round((float) $avg, 1) : null;
    }
    return $this->ratings()->avg('rating');
}
```

Step 2: Update queries to pre-load aggregates:
```php
// CourseController.php
$query = Course::query()
    ->with(['category', 'user', 'tags'])
    ->withCount(['sections', 'lessons', 'enrollments', 'ratings'])
    ->withAvg('ratings', 'rating');

// Now accessors use pre-loaded values - 0 extra queries!
```

**Key Laravel Methods:**
```php
// Pre-load counts
->withCount(['lessons', 'ratings'])
// Produces: lessons_count, ratings_count

// Pre-load averages
->withAvg('ratings', 'rating')
// Produces: ratings_avg_rating

// Pre-load sums
->withSum('items', 'price')
// Produces: items_sum_price

// For single model (not query builder)
$course->loadCount(['lessons', 'ratings']);
$course->loadAvg('ratings', 'rating');
```

## Controller/Transformer N+1 Trap

Data transformation methods (like `toArray()` callbacks, `map()` functions, or custom transformers) often hide N+1 queries:

**The Problem:**
```php
// LearningPathEnrollmentController.php - HIDDEN N+1!
protected function transformProgress($progress): array
{
    $transformedCourses = array_map(function ($course) {
        // QUERY per course!
        $courseModel = Course::find($course['course_id']);

        // ANOTHER QUERY per course!
        $lessonsCount = $courseModel->lessons()->count();

        // TWO MORE QUERIES per course!
        if (isset($course['enrollment_id'])) {
            $enrollment = Enrollment::find($course['enrollment_id']);
            $completedLessons = $enrollment->lessonProgress()
                ->where('is_completed', true)->count();
            $timeSpent = $enrollment->lessonProgress()
                ->sum('time_spent_seconds');
        }

        return array_merge($course, [...]);
    }, $courses);
}
// 10 courses = 50+ queries!
```

**The Fix: Batch Load + DB Aggregation**
```php
protected function transformProgress($progress): array
{
    $courses = $progress->toResponse()['courses'];

    // Batch load course data (1 query)
    $courseIds = array_column($courses, 'course_id');
    $courseModels = Course::query()
        ->whereIn('id', $courseIds)
        ->withCount('lessons')
        ->get()
        ->keyBy('id');

    // Batch load lesson progress stats (1 query with aggregation)
    $enrollmentIds = array_filter(array_column($courses, 'enrollment_id'));
    $lessonProgressStats = [];

    if (! empty($enrollmentIds)) {
        $lessonProgressStats = DB::table('lesson_progress')
            ->whereIn('enrollment_id', $enrollmentIds)
            ->selectRaw('enrollment_id,
                SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
                SUM(time_spent_seconds) as total_time_spent')
            ->groupBy('enrollment_id')
            ->get()
            ->keyBy('enrollment_id');
    }

    // Transform using pre-loaded data (0 queries)
    $transformedCourses = array_map(function ($course) use ($courseModels, $lessonProgressStats) {
        $courseModel = $courseModels->get($course['course_id']);
        $stats = $lessonProgressStats[$course['enrollment_id']] ?? null;

        return array_merge($course, [
            'lessons_count' => $courseModel?->lessons_count ?? 0,
            'completed_lessons' => (int) ($stats?->completed_count ?? 0),
            'time_spent_minutes' => (int) (($stats?->total_time_spent ?? 0) / 60),
        ]);
    }, $courses);

    return [...];
}
// 10 courses = 2 queries (96% reduction!)
```

**Key Patterns:**
1. Extract all IDs before the loop with `array_column()`
2. Batch load models with `whereIn()->get()->keyBy()`
3. Use `DB::table()` with `selectRaw()` for aggregations
4. Use `groupBy()` in SQL for per-item stats
5. Access pre-loaded data in the loop with collection `get()`

---

## RequiresEagerLoading Trait (Aggressive N+1 Detection)

The accessor patterns above rely on developers remembering to check for pre-loaded values. For more aggressive detection, use the `RequiresEagerLoading` trait which **throws exceptions in dev/testing** when aggregates are accessed without eager loading.

### The Trait

```php
// app/Models/Concerns/RequiresEagerLoading.php
namespace App\Models\Concerns;

use Illuminate\Support\Facades\Log;

trait RequiresEagerLoading
{
    /**
     * Get eager-loaded count or throw in dev/log in prod.
     */
    protected function getEagerCount(string $relation): int
    {
        $attribute = "{$relation}_count";

        if (array_key_exists($attribute, $this->attributes)) {
            return (int) $this->attributes[$attribute];
        }

        return $this->handleMissingEagerLoad($attribute, "withCount('{$relation}')");
    }

    /**
     * Get eager-loaded average or throw in dev/log in prod.
     */
    protected function getEagerAvg(string $relation, string $column): ?float
    {
        $attribute = "{$relation}_avg_{$column}";

        if (array_key_exists($attribute, $this->attributes)) {
            $value = $this->attributes[$attribute];
            return $value !== null ? round((float) $value, 1) : null;
        }

        return $this->handleMissingEagerLoad($attribute, "withAvg('{$relation}', '{$column}')");
    }

    protected function handleMissingEagerLoad(string $attribute, string $suggestion): mixed
    {
        $message = "N+1 query detected: {$this::class}::{$attribute} accessed without {$suggestion}. "
            ."Add ->{$suggestion} to your query to fix this.";

        // In development/testing: fail fast to catch issues early
        if (app()->environment('local', 'testing')) {
            throw new \RuntimeException($message);
        }

        // In production: log warning and fallback gracefully
        Log::warning($message, ['model' => $this::class, 'id' => $this->id]);

        return null;
    }
}
```

### Using in Models

```php
// app/Models/Course.php
use App\Models\Concerns\RequiresEagerLoading;

class Course extends Model
{
    use RequiresEagerLoading;

    public function getTotalLessonsAttribute(): int
    {
        return $this->getEagerCount('lessons');
    }

    public function getAverageRatingAttribute(): ?float
    {
        return $this->getEagerAvg('ratings', 'rating');
    }

    public function getRatingsCountAttribute(): int
    {
        return $this->getEagerCount('ratings');
    }
}
```

### Controllers Must Eager Load

```php
// ❌ BAD: Will throw RuntimeException in dev/testing
$courses = Course::all();
foreach ($courses as $course) {
    echo $course->total_lessons;  // THROWS!
}

// ✅ GOOD: Eager load aggregates
$courses = Course::query()
    ->withCount(['lessons', 'ratings'])
    ->withAvg('ratings', 'rating')
    ->get();

foreach ($courses as $course) {
    echo $course->total_lessons;    // Works - uses pre-loaded value
    echo $course->average_rating;   // Works - uses pre-loaded value
}
```

### Test Impact

Tests that create models without eager loading will fail:

```php
// ❌ BAD: Test will throw
it('shows course total lessons', function () {
    $course = Course::factory()->create();
    expect($course->total_lessons)->toBe(0);  // THROWS!
});

// ✅ GOOD: Reload with eager loading
it('shows course total lessons', function () {
    $course = Course::factory()->create();

    // Reload with eager-loaded count
    $course = Course::withCount('lessons')->find($course->id);
    expect($course->total_lessons)->toBe(0);  // Works
});
```

### Benefits

| Environment | Behavior |
|-------------|----------|
| `local` / `testing` | Throws exception immediately - catches N+1 during development |
| `production` | Logs warning, returns null - graceful degradation |

This is more aggressive than the "check for pre-loaded values" pattern, but catches issues during development rather than in production.

---

## Nested Loop Aggregation: SQL Join Pattern

**Problem:** Nested foreach loops for sum/count across related tables.

```php
// ❌ BAD: Nested N+1 (1 + N + N*M queries!)
public function calculateEstimatedDuration(): int
{
    $totalMinutes = 0;
    foreach ($this->sections as $section) {        // 1 query for N sections
        foreach ($section->lessons as $lesson) {    // N queries for M lessons each
            $totalMinutes += $lesson->estimated_duration_minutes ?? 0;
        }
    }
    return $totalMinutes;
}
// 10 sections × 5 lessons = 11 queries minimum
```

**Fix: Single SQL Query with JOIN**
```php
// ✅ GOOD: 1 query total using DB::table with join
use Illuminate\Support\Facades\DB;

public function calculateEstimatedDuration(): int
{
    return (int) DB::table('lessons')
        ->join('course_sections', 'lessons.course_section_id', '=', 'course_sections.id')
        ->where('course_sections.course_id', $this->id)
        ->whereNull('lessons.deleted_at')        // Handle soft deletes
        ->whereNull('course_sections.deleted_at')
        ->sum('lessons.estimated_duration_minutes');
}
// Always 1 query regardless of data size!
```

**For simpler single-level aggregation:**
```php
// CourseSection duration (no join needed)
public function calculateEstimatedDuration(): int
{
    return (int) DB::table('lessons')
        ->where('course_section_id', $this->id)
        ->whereNull('deleted_at')
        ->sum('estimated_duration_minutes');
}
```

**Key Points:**
1. Use `DB::table()` for aggregations (SUM, COUNT, AVG) - CLAUDE.md guideline
2. Always handle soft deletes with `whereNull('deleted_at')`
3. Cast result to `(int)` since `sum()` returns string/float
4. Join tables for multi-level aggregations instead of nested loops
5. Let the database do the math - it's optimized for this

---

## Files to Reference

```
app/Domain/Progress/Strategies/AssessmentInclusiveProgressCalculator.php  # Fixed N+1 example
app/Domain/Progress/Strategies/LessonBasedProgressCalculator.php          # Progress calculation
app/Models/Course.php                                                      # SQL join aggregation pattern
app/Models/CourseSection.php                                               # Simple DB aggregation pattern
app/Models/Assessment.php                                                  # Accessor with pre-load pattern
app/Http/Controllers/CourseController.php                                  # withCount/withAvg usage
app/Http/Controllers/AssessmentController.php                              # loadCount/loadSum usage
app/Http/Controllers/LearningPathEnrollmentController.php                  # Transformer batch loading
tests/Unit/Domain/Progress/Strategies/AssessmentInclusiveProgressCalculatorTest.php  # Test patterns
```
