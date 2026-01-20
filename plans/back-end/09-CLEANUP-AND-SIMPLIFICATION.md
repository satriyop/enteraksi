# Phase 9: Cleanup and Simplification

## Overview

This phase removes all deprecated code, backward compatibility hacks, and production-specific infrastructure that clutters the codebase. Since we're in active development (not production), we can safely remove these without needing gradual rollout mechanisms.

### Goals
- **Clean Slate**: Remove all deprecated methods
- **Simplified Codebase**: Remove backward compatibility code
- **Leaner Infrastructure**: Remove production rollout tooling
- **Full Test Coverage**: Safety net tests for each cleanup phase

### Current Technical Debt Inventory

| Category | Location | Issue |
|----------|----------|-------|
| Deprecated Methods | `LessonProgress` | 4 deprecated methods |
| Deprecated Methods | `Enrollment` | 2 deprecated methods |
| Backward Compat | `AssessmentInclusiveProgressCalculator` | try-catch for missing `is_required` column |
| Schema Gap | `assessments` table | Missing `is_required` column |
| Production Tooling | Various | Feature flags, rollback scripts, monitoring |

---

## Phase A: Schema Completion

**Objective**: Add missing database columns that backward compatibility code was working around.

### A.1 Add `is_required` Column to Assessments

The `AssessmentInclusiveProgressCalculator::isComplete()` method has a try-catch block that handles the missing `is_required` column. We need to add this column properly.

```bash
php artisan make:migration add_is_required_to_assessments_table
```

**Migration Content**:
```php
public function up(): void
{
    Schema::table('assessments', function (Blueprint $table) {
        $table->boolean('is_required')->default(true)->after('allow_review');
    });
}

public function down(): void
{
    Schema::table('assessments', function (Blueprint $table) {
        $table->dropColumn('is_required');
    });
}
```

### A.2 Update Assessment Model

Add the `is_required` field to fillable and casts:

```php
// app/Models/Assessment.php
protected $fillable = [
    // ... existing fields
    'is_required',
];

protected function casts(): array
{
    return [
        // ... existing casts
        'is_required' => 'boolean',
    ];
}
```

### A.3 Update AssessmentFactory

```php
// database/factories/AssessmentFactory.php
public function definition(): array
{
    return [
        // ... existing fields
        'is_required' => true,
    ];
}

public function optional(): static
{
    return $this->state(fn (array $attributes) => [
        'is_required' => false,
    ]);
}
```

### Safety Net Tests (Phase A)

Create `tests/Feature/Schema/AssessmentIsRequiredColumnTest.php`:

```php
<?php

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Assessment is_required column', function () {

    it('allows creating required assessment', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);

        expect($assessment->is_required)->toBeTrue();
    });

    it('allows creating optional assessment', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->optional()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect($assessment->is_required)->toBeFalse();
    });

    it('defaults to required', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect($assessment->is_required)->toBeTrue();
    });

    it('can query required assessments', function () {
        $user = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $user->id]);

        Assessment::factory()->count(3)->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);
        Assessment::factory()->count(2)->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => false,
        ]);

        expect(Assessment::where('is_required', true)->count())->toBe(3);
        expect(Assessment::where('is_required', false)->count())->toBe(2);
    });
});
```

**Verification Command**:
```bash
php artisan test --filter=AssessmentIsRequiredColumnTest
```

---

## Phase B: Test Migration to New Services

**Objective**: Update existing tests to use the new service layer instead of deprecated model methods. This ensures services are fully tested before removing deprecated methods.

### B.1 Tests Currently Using Deprecated Methods

| File | Method Used | Count |
|------|-------------|-------|
| `EnrollmentLifecycleTest.php` | `recalculateCourseProgress()` | 14 |
| `EnrollmentLifecycleTest.php` | `getOrCreateProgressForLesson()` | 2 |
| `EdgeCasesAndBusinessRulesTest.php` | `recalculateCourseProgress()` | 5 |

### B.2 Migration Strategy

Replace deprecated method calls with service calls:

**Before**:
```php
$enrollment->recalculateCourseProgress();
```

**After**:
```php
$progressService = app(ProgressTrackingService::class);
$progressService->recalculateCourseProgress($enrollment);
```

**Before**:
```php
$progress = $enrollment->getOrCreateProgressForLesson($lesson);
```

**After**:
```php
$progressService = app(ProgressTrackingService::class);
$progress = $progressService->getOrCreateProgress($enrollment, $lesson);
```

### B.3 Helper Function for Tests

Add to `tests/Pest.php`:

```php
function progressService(): ProgressTrackingService
{
    return app(ProgressTrackingService::class);
}
```

### Safety Net Tests (Phase B)

Create `tests/Feature/Services/ProgressTrackingServiceEquivalenceTest.php`:

```php
<?php

use App\Domain\Progress\Services\ProgressTrackingService;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ProgressTrackingService equivalence to deprecated methods', function () {

    it('recalculateCourseProgress produces same result as service method', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lessons = Lesson::factory()->count(3)->create(['course_id' => $course->id]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete some lessons
        foreach ($lessons->take(2) as $lesson) {
            $enrollment->lessonProgress()->create([
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
                'progress_percentage' => 100,
                'is_completed' => true,
            ]);
        }

        $service = app(ProgressTrackingService::class);
        $service->recalculateCourseProgress($enrollment);

        $enrollment->refresh();

        // 2 of 3 lessons = 66.67%
        expect($enrollment->progress_percentage)->toBeGreaterThan(60);
        expect($enrollment->progress_percentage)->toBeLessThan(70);
    });

    it('getOrCreateProgress creates new progress record', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        $service = app(ProgressTrackingService::class);
        $progress = $service->getOrCreateProgress($enrollment, $lesson);

        expect($progress)->not->toBeNull();
        expect($progress->lesson_id)->toBe($lesson->id);
        expect($progress->user_id)->toBe($user->id);
    });

    it('getOrCreateProgress returns existing progress record', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Create existing progress
        $existingProgress = $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'progress_percentage' => 50,
        ]);

        $service = app(ProgressTrackingService::class);
        $progress = $service->getOrCreateProgress($enrollment, $lesson);

        expect($progress->id)->toBe($existingProgress->id);
        expect($progress->progress_percentage)->toBe(50);
    });
});
```

**Verification Command**:
```bash
php artisan test --filter=ProgressTrackingServiceEquivalenceTest
```

---

## Phase C: Remove Deprecated Methods

**Objective**: Remove all deprecated methods from models after tests are migrated.

### C.1 Methods to Remove from `LessonProgress`

```php
// REMOVE these methods from app/Models/LessonProgress.php

/**
 * @deprecated Use ProgressTrackingService::updateProgress() instead
 */
public function updateProgress(int $percentage, ?int $timeSpent = null): void
{
    // ... entire method
}

/**
 * @deprecated Use ProgressTrackingService::updateProgress() with timeSpentSeconds instead
 */
public function addTimeSpent(int $seconds): void
{
    // ... entire method
}

/**
 * @deprecated Use ProgressTrackingService::updateProgress() with media params instead
 */
public function updateMediaProgress(float $position, float $duration, ?int $playbackSpeed = null): void
{
    // ... entire method
}

/**
 * @deprecated Use ProgressTrackingService::completeLesson() instead
 */
public function markCompleted(): void
{
    // ... entire method
}
```

### C.2 Methods to Remove from `Enrollment`

```php
// REMOVE these methods from app/Models/Enrollment.php

/**
 * @deprecated Use ProgressTrackingService::getOrCreateProgress() instead
 */
public function getOrCreateProgressForLesson(Lesson $lesson): LessonProgress
{
    // ... entire method
}

/**
 * @deprecated Use ProgressTrackingService::recalculateCourseProgress() instead
 */
public function recalculateCourseProgress(): void
{
    // ... entire method
}
```

### Safety Net Tests (Phase C)

Create `tests/Unit/Models/DeprecatedMethodsRemovedTest.php`:

```php
<?php

use App\Models\Enrollment;
use App\Models\LessonProgress;

describe('Deprecated methods are removed', function () {

    describe('LessonProgress deprecated methods', function () {

        it('does not have updateProgress method', function () {
            expect(method_exists(LessonProgress::class, 'updateProgress'))->toBeFalse();
        });

        it('does not have addTimeSpent method', function () {
            expect(method_exists(LessonProgress::class, 'addTimeSpent'))->toBeFalse();
        });

        it('does not have updateMediaProgress method', function () {
            expect(method_exists(LessonProgress::class, 'updateMediaProgress'))->toBeFalse();
        });

        it('does not have markCompleted method', function () {
            expect(method_exists(LessonProgress::class, 'markCompleted'))->toBeFalse();
        });
    });

    describe('Enrollment deprecated methods', function () {

        it('does not have getOrCreateProgressForLesson method', function () {
            expect(method_exists(Enrollment::class, 'getOrCreateProgressForLesson'))->toBeFalse();
        });

        it('does not have recalculateCourseProgress method', function () {
            expect(method_exists(Enrollment::class, 'recalculateCourseProgress'))->toBeFalse();
        });
    });
});
```

**Verification Command**:
```bash
php artisan test --filter=DeprecatedMethodsRemovedTest
```

---

## Phase D: Remove Backward Compatibility Code

**Objective**: Clean up backward compatibility patterns now that schema is complete.

### D.1 Clean Up AssessmentInclusiveProgressCalculator

Remove the try-catch block in `isComplete()`:

**Before**:
```php
// Check if is_required column exists by trying to filter
try {
    $requiredAssessments = $assessmentsQuery
        ->where('is_required', true)
        ->get();

    if ($requiredAssessments->isEmpty()) {
        return true;
    }
} catch (\Exception $e) {
    // Column doesn't exist, use all published assessments
    $requiredAssessments = $assessmentsQuery->get();

    if ($requiredAssessments->isEmpty()) {
        return true;
    }
}
```

**After**:
```php
$requiredAssessments = $assessmentsQuery
    ->where('is_required', true)
    ->get();

// If no required assessments exist, the course is complete if lessons are done
if ($requiredAssessments->isEmpty()) {
    return true;
}
```

### D.2 Simplify FeatureFlag Service (Optional)

Since we're in development, we can simplify the FeatureFlag service to remove:
- Dual-key checking (`use_{feature}` and `{feature}`)
- Percentage rollout logic
- Pilot user logic

Or we can keep it for future use but simplify the config.

**Recommended**: Keep the service structure but set all flags to `true` in config.

### Safety Net Tests (Phase D)

Create `tests/Unit/Domain/Progress/Strategies/AssessmentInclusiveProgressCalculatorCleanTest.php`:

```php
<?php

use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AssessmentInclusiveProgressCalculator with is_required column', function () {

    it('completes when all required assessments passed', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        // Create required and optional assessments
        $requiredAssessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);
        $optionalAssessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => false,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'is_completed' => true,
            'progress_percentage' => 100,
        ]);

        // Pass required assessment only
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $requiredAssessment->id,
            'user_id' => $user->id,
        ]);

        $calculator = new AssessmentInclusiveProgressCalculator();

        // Should be complete - optional assessment not required
        expect($calculator->isComplete($enrollment))->toBeTrue();
    });

    it('does not complete when required assessment not passed', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        $requiredAssessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'is_completed' => true,
            'progress_percentage' => 100,
        ]);

        // No assessment attempt

        $calculator = new AssessmentInclusiveProgressCalculator();

        expect($calculator->isComplete($enrollment))->toBeFalse();
    });

    it('completes when no required assessments exist', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        // Only optional assessments
        Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => false,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'is_completed' => true,
            'progress_percentage' => 100,
        ]);

        $calculator = new AssessmentInclusiveProgressCalculator();

        // No required assessments = complete if lessons done
        expect($calculator->isComplete($enrollment))->toBeTrue();
    });
});
```

**Verification Command**:
```bash
php artisan test --filter=AssessmentInclusiveProgressCalculatorCleanTest
```

---

## Phase E: Remove Production Rollout Infrastructure

**Objective**: Remove production-specific tooling that's not needed during development.

### E.1 Files to Remove

| File | Purpose | Action |
|------|---------|--------|
| `scripts/emergency-rollback.sh` | Emergency rollback script | DELETE |
| `app/Console/Commands/MonitorRollout.php` | Rollout monitoring | DELETE |

### E.2 Config Simplification

Simplify `config/features.php` to just enable all features:

**Before**:
```php
return [
    'use_enrollment_service' => env('FEATURE_ENROLLMENT_SERVICE', true),
    'use_progress_service' => env('FEATURE_PROGRESS_SERVICE', true),
    // ... many env-based flags
    'pilot_user_ids' => array_filter(...),
    'rollout_percentage' => [...],
];
```

**After**:
```php
return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | All features are enabled by default during development.
    | For production rollout, use environment-based flags.
    |
    */

    'use_enrollment_service' => true,
    'use_progress_service' => true,
    'use_grading_service' => true,
    'use_course_state_machine' => true,
    'use_enrollment_state_machine' => true,
    'use_attempt_state_machine' => true,
    'use_domain_events' => true,
    'use_grading_strategies' => true,
];
```

### E.3 Simplify FeatureFlag Service

Simplify to just check config flags:

**After**:
```php
<?php

namespace App\Domain\Shared\Services;

class FeatureFlag
{
    /**
     * Check if a feature is enabled.
     */
    public static function isEnabled(string $feature): bool
    {
        return config("features.use_{$feature}", false);
    }

    /**
     * Get all enabled features.
     *
     * @return array<string>
     */
    public static function getEnabledFeatures(): array
    {
        $features = [
            'enrollment_service',
            'progress_service',
            'grading_service',
            'course_state_machine',
            'enrollment_state_machine',
            'attempt_state_machine',
            'domain_events',
            'grading_strategies',
        ];

        return array_values(array_filter($features, fn ($f) => self::isEnabled($f)));
    }
}
```

### Safety Net Tests (Phase E)

Create `tests/Unit/Domain/Shared/Services/SimplifiedFeatureFlagTest.php`:

```php
<?php

use App\Domain\Shared\Services\FeatureFlag;

describe('Simplified FeatureFlag service', function () {

    it('returns true for enabled feature', function () {
        config(['features.use_enrollment_service' => true]);

        expect(FeatureFlag::isEnabled('enrollment_service'))->toBeTrue();
    });

    it('returns false for disabled feature', function () {
        config(['features.use_enrollment_service' => false]);

        expect(FeatureFlag::isEnabled('enrollment_service'))->toBeFalse();
    });

    it('returns false for non-existent feature', function () {
        expect(FeatureFlag::isEnabled('non_existent_feature'))->toBeFalse();
    });

    it('gets all enabled features', function () {
        config([
            'features.use_enrollment_service' => true,
            'features.use_progress_service' => true,
            'features.use_grading_service' => false,
        ]);

        $enabled = FeatureFlag::getEnabledFeatures();

        expect($enabled)->toContain('enrollment_service');
        expect($enabled)->toContain('progress_service');
        expect($enabled)->not->toContain('grading_service');
    });
});
```

**Verification Command**:
```bash
php artisan test --filter=SimplifiedFeatureFlagTest
```

---

## Implementation Checklist

### Phase A: Schema Completion
- [ ] Create migration for `is_required` column
- [ ] Run migration
- [ ] Update Assessment model (fillable, casts)
- [ ] Update AssessmentFactory
- [ ] Write and run safety tests
- [ ] Run full test suite

### Phase B: Test Migration
- [ ] Identify all tests using deprecated methods
- [ ] Add `progressService()` helper to Pest.php
- [ ] Update EnrollmentLifecycleTest.php
- [ ] Update EdgeCasesAndBusinessRulesTest.php
- [ ] Write and run equivalence tests
- [ ] Run full test suite

### Phase C: Remove Deprecated Methods
- [ ] Remove 4 methods from LessonProgress
- [ ] Remove 2 methods from Enrollment
- [ ] Write and run removal verification tests
- [ ] Run full test suite

### Phase D: Backward Compatibility Cleanup
- [ ] Clean up AssessmentInclusiveProgressCalculator
- [ ] Write and run calculator tests
- [ ] Run full test suite

### Phase E: Production Infrastructure Removal
- [ ] Delete `scripts/emergency-rollback.sh`
- [ ] Delete `app/Console/Commands/MonitorRollout.php`
- [ ] Simplify `config/features.php`
- [ ] Simplify FeatureFlag service
- [ ] Write and run simplified tests
- [ ] Run full test suite

---

## Verification Commands Summary

```bash
# Phase A
php artisan migrate
php artisan test --filter=AssessmentIsRequiredColumnTest

# Phase B
php artisan test --filter=ProgressTrackingServiceEquivalenceTest

# Phase C
php artisan test --filter=DeprecatedMethodsRemovedTest

# Phase D
php artisan test --filter=AssessmentInclusiveProgressCalculatorCleanTest

# Phase E
php artisan test --filter=SimplifiedFeatureFlagTest

# Full verification
php artisan test
```

---

## Rollback Strategy

Since we're in development, rollback is simple:
1. `git stash` or `git checkout .` for uncommitted changes
2. `git revert` for committed changes
3. `php artisan migrate:rollback` for database changes

Each phase should be committed separately to enable granular rollback.

---

## Post-Cleanup Metrics

After completing all phases:

| Metric | Before | After |
|--------|--------|-------|
| Deprecated methods | 6 | 0 |
| Backward compat try-catch | 1 | 0 |
| Production tooling files | 2 | 0 |
| Feature flag complexity | High | Low |
| Lines of code removed | - | ~200 |

---

## Notes

- **Do NOT skip safety tests** - They ensure each phase doesn't break existing functionality
- **Commit after each phase** - Enables granular rollback if needed
- **Run full test suite** - After each phase to catch regressions
- **Order matters** - Phase A must complete before Phase D (schema before cleanup)
