# ROUND 10: Simplification with Spatie Laravel Data

> **Status**: ✅ COMPLETED (2026-01-22)
> **Tests**: 1,362 passing

> **Goal**: Replace custom value objects + DTOs with Spatie Data. Get TypeScript generation as a bonus.

## What Was Done

1. **Installed Spatie packages**: `spatie/laravel-data` and `spatie/laravel-typescript-transformer`
2. **Created 6 Data classes** in `app/Data/`:
   - `EnrollmentData`, `CourseData`, `UserData`, `PathEnrollmentData`, `CourseProgressData`, `LessonProgressData`
3. **Moved behavior to models**: `Enrollment::drop()`, `::complete()`, `::reactivate()`, `LearningPathEnrollment::drop()`, `::complete()`
4. **Services now return Models**:
   - `EnrollmentService::enroll()` returns `Enrollment` instead of `EnrollmentResult`
   - `PathEnrollmentService::enroll()` returns `LearningPathEnrollment` instead of `PathEnrollmentResult`
   - `PathEnrollmentService::reactivatePathEnrollment()` returns `LearningPathEnrollment`
5. **Deleted unused files**:
   - `EnrollmentData` (ValueObject)
   - `EnrollmentResult` (DTO)
   - `PathEnrollmentData` (ValueObject at `app/Domain/LearningPath/ValueObjects/`) - duplicate of Spatie version
   - `PathEnrollmentResult` (DTO)
   - `CourseProgressItem` (DTO) - replaced by `CourseProgressData`
   - `DataTransferObject` base class
   - `ProgressData` (ValueObject) - consolidated into `LessonProgressData` Spatie class
6. **`ProgressResult` updated** to use `LessonProgressData` (Spatie) instead of `ProgressData` (ValueObject)
   - `LessonProgressController` updated to use snake_case properties matching Spatie Data class
7. **Strategy pattern KEPT**: Provides legitimate value for configurable progress calculation
8. **TypeScript types generated**: 6 types in `resources/js/types/generated.d.ts`

---

## Why Spatie Data (Not Pure Resources)

| What We Get | Value |
|-------------|-------|
| TypeScript generation | Auto-sync frontend types with backend |
| Unified pattern | One class for request validation + response transformation |
| Less custom code | Delete our value objects, use battle-tested package |
| Lazy properties | Performance optimization built-in |

---

## Phase 1: Install & Configure Spatie Data

### 1.1 Install Package

```bash
composer require spatie/laravel-data
composer require spatie/laravel-typescript-transformer
php artisan vendor:publish --tag=data-config
php artisan vendor:publish --tag=typescript-transformer-config
```

### 1.2 Configure TypeScript Transformer

```php
// config/typescript-transformer.php
return [
    'auto_discover_transformers' => [
        Spatie\TypeScriptTransformer\Transformers\DtoTransformer::class,
    ],

    'output_file' => resource_path('js/types/generated.d.ts'),

    'searching_path' => app_path('Data'),

    'collectors' => [
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptCollector::class,
    ],
];
```

### 1.3 Add to package.json Scripts

```json
{
  "scripts": {
    "types": "php artisan typescript:transform",
    "dev": "npm run types && vite",
    "build": "npm run types && vite build"
  }
}
```

---

## Phase 2: Create Data Classes

### 2.1 Directory Structure

```
app/Data/
├── Enrollment/
│   ├── EnrollmentData.php        # Replaces EnrollmentResult + EnrollmentData value object
│   └── CreateEnrollmentData.php  # Replaces CreateEnrollmentDTO (optional, can use directly)
├── LearningPath/
│   ├── PathEnrollmentData.php    # Replaces PathEnrollmentResult + PathEnrollmentData
│   ├── PathProgressData.php      # Replaces PathProgressResult
│   └── CourseProgressData.php    # Replaces CourseProgressItem
├── Progress/
│   ├── LessonProgressData.php    # Replaces ProgressResult + ProgressData
│   └── UpdateProgressData.php    # Replaces ProgressUpdateDTO
├── Assessment/
│   └── GradingResultData.php     # Replaces GradingResult
├── Course/
│   └── CourseData.php            # For API responses
└── User/
    └── UserData.php              # For API responses
```

### 2.2 EnrollmentData Example

```php
<?php

namespace App\Data\Enrollment;

use App\Models\Enrollment;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EnrollmentData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $course_id,
        public string $status,
        public int $progress_percentage,
        public ?Carbon $enrolled_at,
        public ?Carbon $started_at,
        public ?Carbon $completed_at,
        public Lazy|CourseData|null $course,
        public Lazy|UserData|null $user,
    ) {}

    public static function fromModel(Enrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id,
            user_id: $enrollment->user_id,
            course_id: $enrollment->course_id,
            status: (string) $enrollment->status,
            progress_percentage: $enrollment->progress_percentage,
            enrolled_at: $enrollment->enrolled_at,
            started_at: $enrollment->started_at,
            completed_at: $enrollment->completed_at,
            course: Lazy::whenLoaded('course', $enrollment, fn() => CourseData::from($enrollment->course)),
            user: Lazy::whenLoaded('user', $enrollment, fn() => UserData::from($enrollment->user)),
        );
    }
}
```

### 2.3 PathEnrollmentData

```php
<?php

namespace App\Data\LearningPath;

use App\Models\LearningPathEnrollment;
use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PathEnrollmentData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $learning_path_id,
        public string $state,
        public int $progress_percentage,
        public int $completed_courses,
        public int $total_courses,
        public ?Carbon $enrolled_at,
        public ?Carbon $started_at,
        public ?Carbon $completed_at,
        /** @var DataCollection<CourseProgressData> */
        public Lazy|DataCollection|null $course_progress,
    ) {}

    public static function fromModel(LearningPathEnrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id,
            user_id: $enrollment->user_id,
            learning_path_id: $enrollment->learning_path_id,
            state: (string) $enrollment->state,
            progress_percentage: $enrollment->progress_percentage,
            completed_courses: $enrollment->completed_courses,
            total_courses: $enrollment->total_courses,
            enrolled_at: $enrollment->enrolled_at,
            started_at: $enrollment->started_at,
            completed_at: $enrollment->completed_at,
            course_progress: Lazy::whenLoaded(
                'courseProgress',
                $enrollment,
                fn() => CourseProgressData::collection($enrollment->courseProgress)
            ),
        );
    }
}
```

### 2.4 CourseProgressData

```php
<?php

namespace App\Data\LearningPath;

use App\Models\LearningPathCourseProgress;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CourseProgressData extends Data
{
    public function __construct(
        public int $id,
        public int $learning_path_enrollment_id,
        public int $course_id,
        public int $position,
        public string $state,
        public ?int $enrollment_id,
        public bool $is_available,
        public bool $prerequisites_met,
    ) {}

    public static function fromModel(LearningPathCourseProgress $progress): self
    {
        return new self(
            id: $progress->id,
            learning_path_enrollment_id: $progress->learning_path_enrollment_id,
            course_id: $progress->course_id,
            position: $progress->position,
            state: (string) $progress->state,
            enrollment_id: $progress->enrollment_id,
            is_available: $progress->is_available,
            prerequisites_met: $progress->prerequisites_met,
        );
    }
}
```

### 2.5 LessonProgressData

```php
<?php

namespace App\Data\Progress;

use App\Models\LessonProgress;
use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LessonProgressData extends Data
{
    public function __construct(
        public int $id,
        public int $enrollment_id,
        public int $lesson_id,
        public bool $is_completed,
        public int $progress_percentage,
        public int $time_spent_seconds,
        public ?Carbon $started_at,
        public ?Carbon $completed_at,
    ) {}

    public static function fromModel(LessonProgress $progress): self
    {
        return new self(
            id: $progress->id,
            enrollment_id: $progress->enrollment_id,
            lesson_id: $progress->lesson_id,
            is_completed: $progress->is_completed,
            progress_percentage: $progress->progress_percentage ?? 0,
            time_spent_seconds: $progress->time_spent_seconds ?? 0,
            started_at: $progress->started_at,
            completed_at: $progress->completed_at,
        );
    }
}
```

### 2.6 Request Data (Replaces Input DTOs)

```php
<?php

namespace App\Data\Progress;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class UpdateProgressData extends Data
{
    public function __construct(
        #[Required]
        public int $lesson_id,

        #[Required, Min(0), Max(100)]
        public int $progress_percentage,

        public ?int $time_spent_seconds = null,
        public ?float $media_position = null,
        public ?int $current_page = null,
        public bool $mark_completed = false,
    ) {}
}
```

---

## Phase 3: Update Services

### 3.1 Services Return Models (Not Data)

Services return Eloquent models. Controllers/callers transform to Data when needed.

```php
// EnrollmentService.php
class EnrollmentService
{
    public function enroll(User $user, Course $course): Enrollment
    {
        // Validation
        if ($this->hasActiveEnrollment($user, $course)) {
            throw new AlreadyEnrolledException($user, $course);
        }

        // Create
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => ActiveState::class,
            'enrolled_at' => now(),
        ]);

        // Event
        UserEnrolled::dispatch($enrollment);

        return $enrollment;
    }

    public function drop(Enrollment $enrollment, ?string $reason = null): Enrollment
    {
        $enrollment->drop($reason); // Model owns behavior
        return $enrollment;
    }
}
```

### 3.2 Controllers Transform to Data

```php
// EnrollmentController.php
class EnrollmentController extends Controller
{
    public function store(Course $course)
    {
        $this->authorize('enroll', $course);

        $enrollment = $this->enrollmentService->enroll(
            auth()->user(),
            $course
        );

        // For Inertia - redirect with flash
        return redirect()
            ->route('courses.learn', $course)
            ->with('success', 'Berhasil mendaftar kursus');
    }

    // For API endpoints
    public function apiStore(Course $course)
    {
        $enrollment = $this->enrollmentService->enroll(
            auth()->user(),
            $course
        );

        return EnrollmentData::from($enrollment);
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['course', 'user', 'lessonProgress']);

        return EnrollmentData::from($enrollment);
    }
}
```

### 3.3 Inertia Pages Get Data

```php
// LearnerDashboardController.php
public function index()
{
    $enrollments = auth()->user()
        ->enrollments()
        ->with(['course.user', 'course.category'])
        ->withCount(['lessonProgress as completed_lessons' => fn($q) => $q->where('is_completed', true)])
        ->latest('enrolled_at')
        ->get();

    return Inertia::render('Learner/Dashboard', [
        'enrollments' => EnrollmentData::collection($enrollments),
    ]);
}
```

---

## Phase 4: Generated TypeScript Types

After running `php artisan typescript:transform`:

```typescript
// resources/js/types/generated.d.ts

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
        course?: App.Data.Course.CourseData | null;
        user?: App.Data.User.UserData | null;
    };
}

declare namespace App.Data.LearningPath {
    export type PathEnrollmentData = {
        id: number;
        user_id: number;
        learning_path_id: number;
        state: string;
        progress_percentage: number;
        completed_courses: number;
        total_courses: number;
        enrolled_at: string | null;
        started_at: string | null;
        completed_at: string | null;
        course_progress?: App.Data.LearningPath.CourseProgressData[] | null;
    };

    export type CourseProgressData = {
        id: number;
        learning_path_enrollment_id: number;
        course_id: number;
        position: number;
        state: string;
        enrollment_id: number | null;
        is_available: boolean;
        prerequisites_met: boolean;
    };
}

declare namespace App.Data.Progress {
    export type LessonProgressData = {
        id: number;
        enrollment_id: number;
        lesson_id: number;
        is_completed: boolean;
        progress_percentage: number;
        time_spent_seconds: number;
        started_at: string | null;
        completed_at: string | null;
    };
}
```

### 4.1 Use in Vue Components

```vue
<script setup lang="ts">
import type { App } from '@/types/generated';

defineProps<{
    enrollments: App.Data.Enrollment.EnrollmentData[];
}>();
</script>

<template>
    <div v-for="enrollment in enrollments" :key="enrollment.id">
        <h3>{{ enrollment.course?.title }}</h3>
        <ProgressBar :value="enrollment.progress_percentage" />
    </div>
</template>
```

---

## Phase 5: Files to DELETE

### Value Objects (DELETE ALL)
```
app/Domain/Enrollment/ValueObjects/EnrollmentData.php       ✅ DELETED
app/Domain/LearningPath/ValueObjects/PathEnrollmentData.php ✅ DELETED
app/Domain/Progress/ValueObjects/ProgressData.php           ✅ DELETED (consolidated into LessonProgressData Spatie)
```

### Result DTOs (DELETE ALL)
```
app/Domain/Enrollment/DTOs/EnrollmentResult.php             ✅ DELETED
app/Domain/LearningPath/DTOs/PathEnrollmentResult.php       ✅ DELETED
app/Domain/LearningPath/DTOs/PathProgressResult.php         KEPT - aggregates computed progress data
app/Domain/LearningPath/DTOs/CourseProgressItem.php         ✅ DELETED (replaced by CourseProgressData)
app/Domain/Progress/DTOs/ProgressResult.php                 KEPT - uses LessonProgressData (Spatie) + aggregates course stats
app/Domain/Assessment/DTOs/GradingResult.php                KEPT - domain-specific result with factory methods
```

### Input DTOs (DELETE - replaced by Spatie Data)
```
app/Domain/Enrollment/DTOs/CreateEnrollmentDTO.php          KEPT - simple input struct
app/Domain/Progress/DTOs/ProgressUpdateDTO.php              KEPT - simple input struct
app/Domain/Shared/DTOs/DataTransferObject.php               ✅ DELETED
```

### Strategies (DELETE - simplify)
```
app/Domain/Progress/Contracts/ProgressCalculatorContract.php
app/Domain/Progress/Strategies/LessonBasedProgressCalculator.php
app/Domain/Progress/Strategies/WeightedProgressCalculator.php
app/Domain/Progress/Strategies/AssessmentInclusiveProgressCalculator.php
```

---

## Phase 6: Move Model Behavior

Same as before - models own their state transitions:

```php
// app/Models/Enrollment.php
class Enrollment extends Model
{
    public function drop(?string $reason = null): void
    {
        if (!$this->isActive()) {
            throw new InvalidEnrollmentStateException('Cannot drop inactive enrollment');
        }

        $this->update([
            'status' => DroppedState::class,
            'dropped_at' => now(),
            'drop_reason' => $reason,
        ]);

        UserDropped::dispatch($this, $reason);
    }

    public function complete(): void
    {
        // ...
    }

    public function reactivate(bool $preserveProgress = true): void
    {
        // ...
    }
}
```

---

## Phase 7: Update Contracts

### Keep Only What's Needed

```php
// Contracts that STAY (for testing/mocking)
app/Domain/Enrollment/Contracts/EnrollmentServiceContract.php  // KEEP - simplified

// Contracts to DELETE
app/Domain/Progress/Contracts/ProgressCalculatorContract.php   // DELETE
app/Domain/Progress/Contracts/ProgressTrackingServiceContract.php  // DELETE or simplify
```

### Simplified Contract

```php
interface EnrollmentServiceContract
{
    public function enroll(User $user, Course $course): Enrollment;
    public function canEnroll(User $user, Course $course): bool;
    public function drop(Enrollment $enrollment, ?string $reason = null): Enrollment;
}
```

---

## Migration Steps

### Step 1: Install Spatie Data
```bash
composer require spatie/laravel-data spatie/laravel-typescript-transformer
php artisan vendor:publish --tag=data-config
php artisan vendor:publish --tag=typescript-transformer-config
```

### Step 2: Create Data Classes
- Create `app/Data/` structure
- One Data class at a time
- Test each transformation

### Step 3: Update Controllers
- Replace DTO usage with Data classes
- Update Inertia props
- Keep services returning models

### Step 4: Move Model Behavior
- Add `drop()`, `complete()`, `reactivate()` to models
- Update services to call model methods

### Step 5: Delete Old Files
- Remove value objects
- Remove old DTOs
- Remove strategies
- Clean up Domain folder

### Step 6: Generate TypeScript
```bash
php artisan typescript:transform
```

### Step 7: Update Vue Components
- Import generated types
- Use in props/emits

### Step 8: Run Tests & Fix
- Many tests will break (DTO assertions)
- Update to use Data classes or model assertions

---

## Final Structure

```
app/
├── Data/                         # Spatie Data classes
│   ├── Enrollment/
│   ├── LearningPath/
│   ├── Progress/
│   ├── Assessment/
│   ├── Course/
│   └── User/
├── Http/
│   ├── Controllers/              # Thin, use Data for responses
│   └── Requests/                 # Keep for complex validation
├── Models/                       # Rich models with behavior
├── Services/                     # Orchestration only
├── Events/
├── Listeners/
├── Policies/
└── States/

resources/js/
├── types/
│   ├── generated.d.ts           # Auto-generated from PHP
│   └── index.ts                 # Re-exports
└── ...
```

---

## Success Criteria

- [x] Spatie Data installed and configured
- [x] All Data classes created in `app/Data/`
- [x] TypeScript types auto-generating (6 types in `resources/js/types/generated.d.ts`)
- [ ] Vue components using generated types (not yet implemented)
- [x] Value objects deleted:
  - `EnrollmentData` (ValueObject)
  - `PathEnrollmentData` (ValueObject) - duplicate of Spatie version
  - `ProgressData` (ValueObject) - consolidated into `LessonProgressData` Spatie class
  - Empty `ValueObjects/` directory removed where applicable
- [x] Old DTOs deleted:
  - `EnrollmentResult`
  - `PathEnrollmentResult`
  - `CourseProgressItem`
  - `DataTransferObject` base class
- [x] Strategy pattern reviewed - **KEPT** (provides real value for progress calculation)
- [x] Models have behavior methods (Enrollment: drop, complete, reactivate; LearningPathEnrollment: drop, complete)
- [x] Services simplified - return Models instead of DTOs:
  - `EnrollmentService::enroll()` → returns `Enrollment`
  - `PathEnrollmentService::enroll()` → returns `LearningPathEnrollment`
  - `PathEnrollmentService::reactivatePathEnrollment()` → returns `LearningPathEnrollment`
- [x] All tests pass (1,362 tests passing)

---

## What We Gain

| Before | After |
|--------|-------|
| Custom value objects (no benefit) | Spatie Data (TypeScript generation) |
| Manual TypeScript types | Auto-generated, always in sync |
| 3 layers (Model → VO → DTO) | 2 layers (Model → Data) |
| ~30 custom files | ~15 Data files + generated types |
| Tests check custom toArray() | Tests check Data::from() |
