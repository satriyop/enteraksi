---
name: enteraksi-db-indexing
description: Database indexing patterns for Enteraksi LMS. Use when optimizing slow queries, adding composite indexes, or identifying missing indexes.
triggers:
  - slow query
  - missing index
  - database index
  - optimize query
  - composite index
  - query performance
  - explain analyze
---

# Enteraksi Database Indexing Pattern

## When to Use This Skill

- Optimizing slow list/filter queries
- Adding indexes for common WHERE + ORDER BY combinations
- Identifying missing indexes from slow query logs
- Creating composite indexes for multi-column filters

## Identifying Missing Indexes

**Signs you need an index:**
1. Slow queries with WHERE clauses on non-indexed columns
2. Queries filtering on multiple columns simultaneously
3. Pagination queries that slow down on later pages

**Use EXPLAIN to diagnose:**
```sql
EXPLAIN SELECT * FROM enrollments
WHERE course_id = 1 AND status = 'active';

-- Look for:
-- type: ALL (full table scan - BAD)
-- type: ref or const (using index - GOOD)
-- key: NULL (no index used - BAD)
```

## Common Index Patterns for Enteraksi

### 1. Composite Index for Filtered Queries

When queries filter by multiple columns:

```php
// Common query pattern
Enrollment::where('course_id', $courseId)
    ->where('status', 'active')
    ->get();

// Migration to add composite index
Schema::table('enrollments', function (Blueprint $table) {
    $table->index(['course_id', 'status'], 'enrollments_course_status_index');
});
```

**Column order matters!** Put the most selective column first, or the column that appears in equality conditions.

### 2. Index for Foreign Key + Status

Most pivot/junction tables benefit from this pattern:

```php
// learning_path_course_progress often filtered by enrollment + state
Schema::table('learning_path_course_progress', function (Blueprint $table) {
    $table->index(['learning_path_enrollment_id', 'state']);
});

// lesson_progress often filtered by enrollment + completion
Schema::table('lesson_progress', function (Blueprint $table) {
    $table->index(['enrollment_id', 'is_completed']);
});
```

### 3. Index for Soft Deletes + Status

When using soft deletes with status filters:

```php
// Courses often queried: published AND not deleted
Schema::table('courses', function (Blueprint $table) {
    $table->index(['status', 'deleted_at']);
});
```

## Real Example: Enrollments Index

**Problem:** Dashboard queries filtering enrollments by course and status were slow.

```php
// This query was doing full table scan
$activeEnrollments = Enrollment::where('course_id', $course->id)
    ->where('status', 'active')
    ->count();
```

**Solution:**
```php
// database/migrations/2026_01_21_xxx_add_course_status_index_to_enrollments_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['course_id', 'status'], 'enrollments_course_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_course_status_index');
        });
    }
};
```

## Index Naming Convention

Use descriptive names: `{table}_{columns}_index`

```php
// Good names
'enrollments_course_status_index'
'lesson_progress_enrollment_completed_index'
'courses_status_deleted_at_index'

// Bad names (auto-generated, hard to manage)
'enrollments_course_id_status_index'  // Too verbose
'idx_1'                                // Meaningless
```

## When NOT to Add Indexes

1. **Low-cardinality columns alone** - Boolean columns or status with few values
2. **Frequently updated columns** - Indexes slow down writes
3. **Small tables** - Tables under 1000 rows rarely benefit
4. **Write-heavy tables** - Each index adds write overhead

## Quick Checklist

Before adding an index:
- [ ] Is this query in a hot path (dashboard, listing)?
- [ ] Does EXPLAIN show table scan?
- [ ] Are there at least 10K+ rows?
- [ ] Is the column used in WHERE, not just SELECT?

When creating the index:
- [ ] Column order matches query patterns
- [ ] Named descriptively for easy management
- [ ] Has both up() and down() migrations

## Files to Reference

```
database/migrations/2026_01_21_160342_add_course_status_index_to_enrollments_table.php
```
