---
name: enteraksi-spatie-data
description: Spatie Laravel Data patterns for API responses and TypeScript generation in Enteraksi LMS. Use when creating Data classes, transforming models for API/Inertia, or generating TypeScript types.
triggers:
  - spatie data
  - data class
  - typescript generation
  - api response
  - inertia props
  - generated.d.ts
  - fromModel
  - Data extends
  - TypeScript attribute
---

# Enteraksi Spatie Laravel Data

## Overview

Enteraksi uses **Spatie Laravel Data** for:
1. API and Inertia response transformations
2. Automatic TypeScript type generation
3. Lightweight data containers with helper methods

## Installation (Already Done)

```bash
composer require spatie/laravel-data spatie/laravel-typescript-transformer
php artisan vendor:publish --tag=typescript-transformer-config
```

## Directory Structure

```
app/Data/                    # Spatie Data classes
├── Course/
│   └── CourseData.php
├── Enrollment/
│   └── EnrollmentData.php
├── LearningPath/
│   ├── CourseProgressData.php
│   └── PathEnrollmentData.php
├── Progress/
│   └── LessonProgressData.php
└── User/
    └── UserData.php

resources/js/types/
└── generated.d.ts           # Auto-generated TypeScript (DO NOT EDIT)
```

## Creating a Data Class

### Basic Pattern

```php
<?php

namespace App\Data\Enrollment;

use App\Models\Enrollment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]  // <-- Required for TypeScript generation
class EnrollmentData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $course_id,
        public string $status,
        public int $progress_percentage,
        public ?string $enrolled_at,      // Dates as ISO strings
        public ?string $started_at,
        public ?string $completed_at,
        public ?int $invited_by = null,   // Optional props last
    ) {}

    /**
     * Factory method from Eloquent model.
     * Handle type coercion here (states to strings, dates to ISO).
     */
    public static function fromModel(Enrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id,
            user_id: $enrollment->user_id,
            course_id: $enrollment->course_id,
            status: (string) $enrollment->status,  // Cast state to string
            progress_percentage: $enrollment->progress_percentage ?? 0,
            enrolled_at: $enrollment->enrolled_at?->toIso8601String(),
            started_at: $enrollment->started_at?->toIso8601String(),
            completed_at: $enrollment->completed_at?->toIso8601String(),
            invited_by: $enrollment->invited_by,
        );
    }

    // Optional: Add helper methods for frontend convenience
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
```

### With Related Data (Nested Factory)

```php
#[TypeScript]
class CourseProgressData extends Data
{
    public function __construct(
        public int $course_id,
        public string $course_title,
        public string $status,
        public int $position,
        public bool $is_required,
        public int $completion_percentage,
        public ?int $min_required_percentage = null,
        /** @var array<int>|null */
        public ?array $prerequisites = null,  // PHPDoc for TypeScript arrays
        public ?string $lock_reason = null,
    ) {}

    /**
     * Factory with pivot data from relationship.
     */
    public static function fromProgress(
        LearningPathCourseProgress $progress,
        array $pivotData = [],
        ?string $lockReason = null
    ): self {
        return new self(
            course_id: $progress->course_id,
            course_title: $progress->course->title ?? 'Unknown',
            status: (string) $progress->state,
            position: $progress->position,
            is_required: $pivotData['is_required'] ?? true,
            completion_percentage: $progress->courseEnrollment?->progress_percentage ?? 0,
            min_required_percentage: $pivotData['min_completion_percentage'] ?? null,
            prerequisites: $pivotData['prerequisites'] ?? null,
            lock_reason: $lockReason,
        );
    }

    // Indonesian status labels for UI
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'locked' => 'Terkunci',
            'available' => 'Tersedia',
            'in_progress' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            default => $this->status,
        };
    }
}
```

## TypeScript Generation

### Generate Types

```bash
php artisan typescript:transform
```

### Output Location

Types are written to `resources/js/types/generated.d.ts`:

```typescript
declare namespace App.Data.Enrollment {
    export type EnrollmentData = {
        id: number;
        user_id: number;
        course_id: number;
        status: string;
        progress_percentage: number;
        enrolled_at: string | null;
        started_at: string | null;
        completed_at: string | null;
        invited_by: number | null;
    };
}
```

### Using in Vue/TypeScript

```typescript
// Import the generated type
type EnrollmentData = App.Data.Enrollment.EnrollmentData;

// Use in component props
defineProps<{
    enrollment: EnrollmentData;
}>();

// Or with Inertia page props
const props = defineProps<{
    enrollments: EnrollmentData[];
}>();
```

## Configuration

See `config/typescript-transformer.php`:

```php
return [
    'auto_discover_types' => [app_path()],

    'collectors' => [
        DefaultCollector::class,  // Finds #[TypeScript] classes
        EnumCollector::class,     // Finds PHP enums
    ],

    'transformers' => [
        SpatieStateTransformer::class,  // spatie/model-states
        EnumTransformer::class,         // PHP enums
        DtoTransformer::class,          // Data classes
    ],

    // Dates become strings in TypeScript
    'default_type_replacements' => [
        DateTime::class => 'string',
        Carbon::class => 'string',
    ],

    'output_file' => resource_path('js/types/generated.d.ts'),
];
```

## When to Use Data Classes

| Scenario | Use Data Class? | Why |
|----------|-----------------|-----|
| API JSON response | ✅ Yes | Type-safe, consistent structure |
| Inertia page props | ✅ Yes | TypeScript types auto-generated |
| Service return value | ❌ No | Return Eloquent model |
| Internal DTO | ❌ No | Use `app/Domain/*/DTOs/` |
| Complex queries | ⚠️ Maybe | Only if you need TypeScript types |

## Controller Usage

```php
// For Inertia pages - pass model, transform in view layer if needed
public function show(Course $course)
{
    return Inertia::render('Courses/Show', [
        'course' => $course->load('sections.lessons'),
        // Or transform if frontend needs specific shape:
        'courseData' => CourseData::fromModel($course),
    ]);
}

// For API responses - always use Data class
public function apiShow(Enrollment $enrollment)
{
    return EnrollmentData::fromModel($enrollment);
}

// For collections
public function apiIndex(Request $request)
{
    $enrollments = Enrollment::where('user_id', $request->user()->id)->get();

    return $enrollments->map(fn ($e) => EnrollmentData::fromModel($e));
}
```

## Key Rules

1. **Always add `#[TypeScript]`** - Without it, no TypeScript types generated
2. **Use `fromModel()` factory** - Keep transformation logic in one place
3. **Dates as ISO strings** - Use `->toIso8601String()` for consistency
4. **Cast states to strings** - `(string) $model->status` for state machines
5. **PHPDoc for arrays** - `/** @var array<int>|null */` for proper TypeScript
6. **Helper methods allowed** - `isActive()`, `getStatusLabel()` are fine
7. **Don't nest models** - Transform related models to their own Data classes

## ❌ Anti-Patterns

```php
// ❌ DON'T: Return raw model from API
public function apiShow(Enrollment $enrollment)
{
    return $enrollment;  // Exposes all attributes, no type safety
}

// ❌ DON'T: Create Data class without #[TypeScript]
class SomeData extends Data { }  // No TypeScript generated

// ❌ DON'T: Use Carbon in constructor
public function __construct(
    public Carbon $enrolled_at,  // TypeScript can't handle this
) {}

// ✅ DO: Use string for dates
public function __construct(
    public ?string $enrolled_at,
) {}
```

## Regenerating After Changes

After modifying Data classes:

```bash
# Regenerate TypeScript types
php artisan typescript:transform

# Restart Vite dev server (if running)
# Or rebuild for production
npm run build
```

## Files to Reference

```
app/Data/Enrollment/EnrollmentData.php      # Basic example
app/Data/LearningPath/CourseProgressData.php # With pivot data
config/typescript-transformer.php            # Configuration
resources/js/types/generated.d.ts            # Generated output
```
