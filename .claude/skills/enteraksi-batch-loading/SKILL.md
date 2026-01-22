---
name: enteraksi-batch-loading
description: Batch loading patterns to eliminate N+1 queries in Enteraksi LMS. Use when loading related data for multiple items, aggregating stats, or optimizing dashboard/list queries.
triggers:
  - batch load
  - load related data
  - optimize query
  - dashboard query
  - aggregate stats
  - multiple items
  - whereIn
  - N+1 fix
  - eager load
  - list performance
---

# Enteraksi Batch Loading Pattern

## When to Use This Skill

- Loading related data for a collection of items (e.g., progress for multiple enrollments)
- Calculating stats/aggregates for multiple records
- Building dashboards or list views with related counts
- Replacing loops that query inside them

## The Pattern

**Instead of querying in a loop:**
```php
// BAD: N+1 queries - one query per enrollment
foreach ($enrollments as $enrollment) {
    $progress = LessonProgress::where('enrollment_id', $enrollment->id)->get();
    // Process...
}
```

**Load all data in one query, then map:**
```php
// GOOD: 2 queries total - one for enrollments, one for all progress
$enrollmentIds = $enrollments->pluck('id');

$progressMap = LessonProgress::query()
    ->whereIn('enrollment_id', $enrollmentIds)
    ->get()
    ->groupBy('enrollment_id');

foreach ($enrollments as $enrollment) {
    $progress = $progressMap->get($enrollment->id, collect());
    // Process...
}
```

## Real Example: Assessment Stats Batch Loading

From `AssessmentInclusiveProgressCalculator`:

```php
/**
 * Get passed assessment IDs for a user with a single query.
 *
 * Instead of checking each assessment individually,
 * we batch load all passed attempts and filter in PHP.
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

/**
 * Usage - calculate progress for all assessments with 2 queries:
 * 1. Get all assessments for course
 * 2. Get all passed assessment IDs for user
 */
public function calculate(Enrollment $enrollment): Percentage
{
    // Query 1: Get all required assessments
    $requiredAssessments = $enrollment->course->assessments()
        ->published()
        ->where('is_required', true)
        ->select('id')
        ->get();

    if ($requiredAssessments->isEmpty()) {
        return Percentage::full();
    }

    // Query 2: Batch load all passed assessments
    $passedIds = $this->getPassedAssessmentIds(
        $enrollment->user_id,
        $requiredAssessments->pluck('id')
    );

    // Filter in PHP - no more queries
    $passedCount = $requiredAssessments
        ->whereIn('id', $passedIds)
        ->count();

    return Percentage::fromFraction($passedCount, $requiredAssessments->count());
}
```

## Enrollment Status Map Pattern

For browse pages showing enrollment status on course cards:

```php
// In CourseController::index()

// Get all course IDs from paginated results
$courseIds = $courses->pluck('id')->toArray();

// Single query: Get all user enrollments for these courses
$enrollmentMap = $user->enrollments()
    ->whereIn('course_id', $courseIds)
    ->get()
    ->mapWithKeys(fn ($e) => [$e->course_id => [
        'status' => $e->status->getValue(),
        'progress_percentage' => $e->progress_percentage,
    ]])
    ->toArray();

// Pass to frontend - O(1) lookup per course
return Inertia::render('courses/Browse', [
    'courses' => $courses,
    'enrollmentMap' => $enrollmentMap,
]);
```

## Progress Stats Aggregation

```php
/**
 * Get assessment stats for enrollment with minimal queries.
 */
public function getAssessmentStats(Enrollment $enrollment): array
{
    // Query 1: All assessments for course
    $assessments = $enrollment->course->assessments()
        ->published()
        ->select('id', 'is_required')
        ->get();

    if ($assessments->isEmpty()) {
        return $this->emptyStats();
    }

    // Query 2: All passed assessment IDs
    $passedIds = $this->getPassedAssessmentIds(
        $enrollment->user_id,
        $assessments->pluck('id')
    );

    // All calculations done in PHP with collections
    $requiredAssessments = $assessments->where('is_required', true);
    $requiredPassedCount = $requiredAssessments
        ->whereIn('id', $passedIds)
        ->count();

    return [
        'total' => $assessments->count(),
        'passed' => $passedIds->count(),
        'pending' => $assessments->count() - $passedIds->count(),
        'required_total' => $requiredAssessments->count(),
        'required_passed' => $requiredPassedCount,
        'required_pending' => max(0, $requiredAssessments->count() - $requiredPassedCount),
    ];
}
```

## Key Principles

1. **Extract IDs first** - Use `pluck('id')` to get collection of IDs
2. **Guard empty collections** - Check `isEmpty()` before querying
3. **Use `whereIn`** - Replace loop queries with single batch query
4. **GroupBy for lookup** - Use `groupBy()` to create O(1) lookup map
5. **Filter in PHP** - After batch loading, use collection methods

## When NOT to Use

- Single item lookups (just use Eloquent relationships)
- When eager loading with `with()` is simpler
- When you need complex JOINs that must happen in SQL

## Quick Reference

```php
// Pattern 1: Simple batch load with groupBy
$itemIds = $items->pluck('id');
$relatedMap = Related::whereIn('item_id', $itemIds)
    ->get()
    ->groupBy('item_id');

// Pattern 2: Aggregates with batch
$counts = DB::table('related')
    ->selectRaw('item_id, COUNT(*) as count')
    ->whereIn('item_id', $itemIds)
    ->groupBy('item_id')
    ->pluck('count', 'item_id');

// Pattern 3: Key-value map
$statusMap = Model::whereIn('id', $ids)
    ->get()
    ->mapWithKeys(fn ($m) => [$m->id => $m->status]);
```

## DB::table Aggregation Pattern

For stats across multiple records, use `DB::table()` with `selectRaw()` instead of Eloquent:

```php
// Get lesson progress stats for multiple enrollments in 1 query
$enrollmentIds = [1, 2, 3, 4, 5];

$stats = DB::table('lesson_progress')
    ->whereIn('enrollment_id', $enrollmentIds)
    ->selectRaw('
        enrollment_id,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
        SUM(time_spent_seconds) as total_time_spent,
        COUNT(*) as total_count
    ')
    ->groupBy('enrollment_id')
    ->get()
    ->keyBy('enrollment_id');

// Access stats for each enrollment (0 queries)
foreach ($enrollments as $enrollment) {
    $enrollmentStats = $stats->get($enrollment->id);
    $completedLessons = (int) ($enrollmentStats?->completed_count ?? 0);
    $timeSpentMinutes = (int) (($enrollmentStats?->total_time_spent ?? 0) / 60);
}
```

**Why DB::table instead of Eloquent?**
- No model hydration overhead
- Raw SQL aggregations are more efficient
- Perfect for read-only stats that don't need events/casts

**Common Aggregation Patterns:**
```php
// Count with condition (CASE WHEN)
SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count

// Boolean count (MySQL/SQLite)
SUM(is_completed) as completed_count  -- Only if column is 0/1

// Average
AVG(score) as average_score

// Multiple stats in one query
selectRaw('
    item_id,
    COUNT(*) as total,
    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed,
    AVG(score) as avg_score,
    MAX(created_at) as last_attempt
')
```

---

## Files to Reference

```
app/Domain/Progress/Strategies/AssessmentInclusiveProgressCalculator.php  # Batch loading example
app/Http/Controllers/CourseController.php                                  # Enrollment map pattern
app/Http/Controllers/LearningPathEnrollmentController.php                  # DB::table aggregation
app/Domain/Progress/Services/ProgressTrackingService.php                   # Stats aggregation
```
