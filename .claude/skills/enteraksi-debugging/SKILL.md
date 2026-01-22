---
name: enteraksi-debugging
description: Root cause analysis patterns for Enteraksi. Use when debugging type errors, data transformation issues, or encountering unexpected behavior to find and fix the source rather than applying workarounds.
triggers:
  - type error
  - TypeError
  - type mismatch
  - data mismatch
  - unexpected type
  - workaround
  - quick fix
  - pivot model
  - json cast
  - array cast
  - relationship issue
  - data transformation
  - debug
  - root cause
---

# Enteraksi Debugging & Root Cause Analysis

## When to Use This Skill

- Encountering TypeError or type mismatch errors
- Data arriving in unexpected format (string vs array, etc.)
- Tempted to add a workaround/quick fix
- Debugging relationship or pivot data issues
- Tracing data flow through layers

## Core Principle: Fix the Source, Not the Symptom

When you encounter a type mismatch or unexpected data format, **DO NOT** immediately add a workaround at the point of failure. Instead:

1. **Trace backwards** - Follow the data from the error point back to its origin
2. **Search for existing patterns** - Check if infrastructure exists but isn't connected
3. **Fix at the source** - Apply the fix where data is first transformed/loaded

## Case Study: Prerequisites Array vs String

### The Problem
```php
// CourseProgressItem.php - TypeError
public function __construct(
    public readonly ?array $prerequisites, // Expected array
    // ...
)

// Error: Argument $prerequisites must be of type ?array, string given
// Value received: "[6]" (JSON string instead of array)
```

### The Wrong Fix (Workaround)
```php
// DON'T DO THIS - Fixing at symptom, not source
prerequisites: is_string($pivotData['prerequisites'])
    ? json_decode($pivotData['prerequisites'], true)
    : ($pivotData['prerequisites'] ?? null),
```

### The Right Fix (Root Cause)

**Step 1: Trace the data flow**
```
Database (JSON column)
  → Eloquent Relationship
    → Pivot data
      → DTO
```

**Step 2: Search for existing patterns**
```bash
# Check if a custom pivot model exists
grep -r "extends Pivot" app/Models/

# Check if relationship uses custom pivot
grep -r "->using(" app/Models/

# Found: LearningPathCourse.php exists with proper casts!
```

**Step 3: Identify the disconnect**
```php
// LearningPathCourse.php - Already has correct cast!
protected function casts(): array
{
    return [
        'prerequisites' => 'array', // This SHOULD work
    ];
}

// BUT LearningPath.php wasn't using it!
public function courses(): BelongsToMany
{
    return $this->belongsToMany(Course::class, 'learning_path_course')
        // Missing: ->using(LearningPathCourse::class)
        ->withPivot(['prerequisites', ...])
}
```

**Step 4: Apply the proper fix**
```php
// LearningPath.php - Add the pivot model
public function courses(): BelongsToMany
{
    return $this->belongsToMany(Course::class, 'learning_path_course')
        ->using(LearningPathCourse::class)  // ✓ Now casts are applied
        ->withPivot(['prerequisites', ...])
}
```

## Debugging Checklist

When encountering type/data mismatch errors:

### 1. Identify the Data Source
```bash
# Where does this data originate?
# - Database column (what type?)
# - API response
# - Form input
# - Cache
```

### 2. Trace the Transformation Chain
```bash
# For Eloquent relationships:
# Database → Model → Relationship → Pivot → Consumer

# Check each step:
# - Column type in migration (text vs json)
# - Model casts
# - Pivot model casts
# - Relationship configuration
```

### 3. Search for Existing Infrastructure
```bash
# Custom pivot models
grep -r "extends Pivot" app/Models/

# Pivot model usage in relationships
grep -r "->using(" app/Models/

# Existing casts
grep -r "'array'" app/Models/
grep -r "'json'" app/Models/

# DTOs/Value Objects that might transform data
grep -r "fromArray\|fromModel" app/Domain/
```

### 4. Verify the Fix Works End-to-End
```php
// Raw database value (JSON string is correct)
DB::table('learning_path_course')->first()->prerequisites
// "[6]"

// Through relationship with pivot model (now returns array)
$learningPath->courses->first()->pivot->prerequisites
// [6]
```

## Common Patterns in Enteraksi

### Custom Pivot Models
```php
// When you need casts on pivot data, create a Pivot model
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class LearningPathCourse extends Pivot
{
    protected $table = 'learning_path_course';

    protected function casts(): array
    {
        return [
            'prerequisites' => 'array',
            'position' => 'integer',
            'is_required' => 'boolean',
        ];
    }
}

// Then use it in the relationship
public function courses(): BelongsToMany
{
    return $this->belongsToMany(Course::class, 'learning_path_course')
        ->using(LearningPathCourse::class)  // <-- Critical!
        ->withPivot([...]);
}
```

### JSON Column Best Practice
```php
// Migration: Use json() not text() for structured data
$table->json('prerequisites')->nullable();

// Model cast: Define the transformation
protected function casts(): array
{
    return ['prerequisites' => 'array'];
}

// Seeder: Use arrays, not json_encode()
'prerequisites' => $position > 0 ? [$previousCourseId] : null,
```

## Red Flags That Suggest Wrong Fix Location

1. **Adding json_decode/json_encode at consumption point** - Usually means cast is missing upstream
2. **Adding type coercion in DTO/Value Object** - Data should arrive correctly
3. **Checking `is_string()` before processing** - Source should provide correct type
4. **Multiple places fixing the same transformation** - Single source of truth missing

## Verification After Fix

```bash
# Run related tests
php artisan test --filter=LearningPath

# Verify data transformation
php artisan tinker
>>> $lp = LearningPath::with('courses')->first()
>>> $lp->courses->first()->pivot->prerequisites
=> [6]  // Should be array, not "[6]"
```

## Quick Reference

```bash
# Search commands for debugging
grep -r "extends Pivot" app/Models/           # Find pivot models
grep -r "->using(" app/Models/                # Check pivot usage
grep -r "'array'" app/Models/                 # Find array casts
grep -r "json_decode\|json_encode" app/       # Find manual conversions

# Common fixes
# 1. Add ->using(PivotModel::class) to relationship
# 2. Create migration: $table->json()->change()
# 3. Add cast to model: 'field' => 'array'
# 4. Update seeder: use array instead of json_encode()
```
