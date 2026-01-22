---
name: enteraksi-testing
description: Pest testing conventions and patterns for Enteraksi LMS. Use when writing tests, understanding test organization, or using factory states.
triggers:
  - write test
  - create test
  - pest test
  - factory state
  - test helper
  - policy test
  - authorization test
  - feature test
  - unit test
  - domain test
  - RefreshDatabase
  - test setup
---

# Enteraksi Testing Patterns

## When to Use This Skill

- Writing new Pest tests (Feature or Unit)
- Using factory states in tests
- Writing policy/authorization tests
- Understanding test organization
- Using global test helpers
- Creating custom assertions

## Test Organization

```
tests/
├── Feature/                      # Integration tests (HTTP, database)
│   ├── Api/                      # API endpoint tests
│   ├── Auth/                     # Authentication flow tests
│   ├── Authorization/            # Policy integration tests
│   ├── ContentManagement/        # Content CRUD tests
│   ├── Journey/                  # User journey/E2E tests
│   └── *Test.php                 # Main feature tests
├── Unit/                         # Unit tests (no database needed*)
│   ├── Domain/                   # Domain layer tests
│   │   ├── Assessment/
│   │   │   ├── Strategies/       # Grading strategy tests
│   │   │   └── ValueObjects/     # Value object tests
│   │   ├── Progress/             # Progress calculator tests
│   │   └── Shared/               # Shared domain tests
│   ├── Models/                   # Model-specific tests
│   └── Policies/                 # Policy unit tests
├── Pest.php                      # Global helpers & configuration
└── TestCase.php                  # Custom assertions & setup
```

*Note: RefreshDatabase is used in both Feature AND Unit tests in this project.

## Pest Configuration (tests/Pest.php)

```php
<?php

// Apply TestCase and RefreshDatabase to both Feature and Unit tests
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit');

// Custom expectations
expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

// ==========================================================================
// Global Helper Functions
// ==========================================================================

/**
 * Create and authenticate a user with a specific role.
 */
function asRole(string $role): Tests\TestCase
{
    $user = App\Models\User::factory()->create(['role' => $role]);
    return test()->actingAs($user);
}

function asAdmin(): Tests\TestCase
{
    return asRole('lms_admin');
}

function asContentManager(): Tests\TestCase
{
    return asRole('content_manager');
}

function asLearner(): Tests\TestCase
{
    return asRole('learner');
}

/**
 * Create a published course with content.
 */
function createPublishedCourseWithContent(int $sectionCount = 1, int $lessonsPerSection = 3): App\Models\Course
{
    $course = App\Models\Course::factory()->published()->public()->create();

    for ($i = 0; $i < $sectionCount; $i++) {
        $section = App\Models\CourseSection::factory()->create([
            'course_id' => $course->id,
            'order' => $i + 1,
        ]);

        App\Models\Lesson::factory()->count($lessonsPerSection)->create([
            'course_section_id' => $section->id,
        ]);
    }

    return $course;
}

/**
 * Create an enrolled user for a course.
 */
function createEnrolledLearner(?App\Models\Course $course = null): array
{
    $user = App\Models\User::factory()->create(['role' => 'learner']);
    $course = $course ?? App\Models\Course::factory()->published()->create();

    $enrollment = App\Models\Enrollment::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'status' => 'active',
    ]);

    return ['user' => $user, 'course' => $course, 'enrollment' => $enrollment];
}

function progressService(): App\Domain\Progress\Services\ProgressTrackingService
{
    return app(App\Domain\Progress\Contracts\ProgressTrackingServiceContract::class);
}
```

## Custom Assertions (tests/TestCase.php)

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();  // Disable Vite to avoid manifest errors
    }

    /**
     * Assert that a domain event was logged.
     */
    protected function assertEventLogged(string $eventName, array $metadata = []): void
    {
        $query = DB::table('domain_event_log')
            ->where('event_name', $eventName);

        foreach ($metadata as $key => $value) {
            $query->whereJsonContains("metadata->{$key}", $value);
        }

        $this->assertTrue(
            $query->exists(),
            "Event '{$eventName}' was not logged with the expected metadata."
        );
    }

    /**
     * Assert that a model has a specific state.
     */
    protected function assertModelState(object $model, string $expectedState): void
    {
        $actualState = $model->fresh()->status;
        $actualStateName = is_string($actualState) ? $actualState : class_basename($actualState);

        $this->assertEquals(
            $expectedState,
            $actualStateName,
            "Expected model state to be '{$expectedState}', got '{$actualStateName}'."
        );
    }

    /**
     * Create a user enrolled in a published course.
     */
    protected function createEnrolledUser(?Course $course = null): array
    {
        $user = User::factory()->create();
        $course = $course ?? Course::factory()->published()->create();

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        return [$user, $course, $enrollment];
    }
}
```

## Key Patterns

### 1. Feature Test (Class-Based)

```php
// tests/Feature/EnrollmentLifecycleTest.php
<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private User $admin;
    private Course $publicCourse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->admin = User::factory()->create(['role' => 'lms_admin']);

        $this->publicCourse = Course::factory()
            ->published()
            ->create(['visibility' => 'public']);

        $this->addContentToCourse($this->publicCourse);
    }

    private function addContentToCourse(Course $course): void
    {
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);
    }

    public function test_learner_can_self_enroll_in_public_course(): void
    {
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->publicCourse->id}/enroll");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
            'status' => 'active',
        ]);
    }
}
```

### 2. Unit Test (Pest describe/it Pattern)

```php
// tests/Unit/Domain/Assessment/Strategies/MultipleChoiceGradingStrategyTest.php
<?php

use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MultipleChoiceGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new MultipleChoiceGradingStrategy;
    });

    it('supports multiple choice questions', function () {
        $question = Question::factory()->multipleChoice()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('does not support essay questions', function () {
        $question = Question::factory()->essay()->create();

        expect($this->strategy->supports($question))->toBeFalse();
    });

    it('grades correct single choice answer', function () {
        $question = Question::factory()->multipleChoice()->create(['points' => 10]);

        $correctOption = QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
        ]);

        QuestionOption::factory()->incorrect()->create([
            'question_id' => $question->id,
        ]);

        $result = $this->strategy->grade($question, $correctOption->id);

        expect($result->isCorrect)->toBeTrue();
        expect($result->score)->toBe(10.0);
        expect($result->feedback)->toBe('Jawaban benar!');
    });
});
```

### 3. Policy Testing

```php
// tests/Unit/Policies/CoursePolicyTest.php
<?php

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CoursePolicy', function () {
    describe('update', function () {
        it('allows content manager to update own draft course', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $contentManager->id]);

            expect($contentManager->can('update', $course))->toBeTrue();
        });

        it('denies content manager from updating published course', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $contentManager->id]);

            expect($contentManager->can('update', $course))->toBeFalse();
        });

        it('allows lms_admin to update any course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->create();

            expect($admin->can('update', $course))->toBeTrue();
        });

        it('denies learner from updating courses', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->draft()->create();

            expect($learner->can('update', $course))->toBeFalse();
        });
    });
});
```

## Factory States Reference

### Course Factory
```php
Course::factory()->draft()->create();           // Draft course
Course::factory()->published()->create();       // Published course
Course::factory()->archived()->create();        // Archived course
Course::factory()->public()->create();          // Public visibility
Course::factory()->restricted()->create();      // Restricted visibility
Course::factory()->beginner()->create();        // Beginner difficulty

// Combine states
Course::factory()->published()->public()->beginner()->create();
```

### Enrollment Factory
```php
Enrollment::factory()->active()->create();      // Active enrollment
Enrollment::factory()->completed()->create();   // Completed enrollment
Enrollment::factory()->dropped()->create();     // Dropped enrollment
```

### Question Factory
```php
Question::factory()->multipleChoice()->create();
Question::factory()->trueFalse()->create();
Question::factory()->essay()->create();
Question::factory()->shortAnswer()->create();
```

### QuestionOption Factory
```php
QuestionOption::factory()->correct()->create(['question_id' => $q->id]);
QuestionOption::factory()->incorrect()->create(['question_id' => $q->id]);
```

### Assessment Factory
```php
Assessment::factory()->published()->create();
Assessment::factory()->withQuestions(5)->create();
Assessment::factory()->timed(30)->create();     // 30 minute time limit
```

## Testing Commands

```bash
# Run all tests
php artisan test

# Run specific file
php artisan test tests/Feature/EnrollmentLifecycleTest.php

# Filter by test name
php artisan test --filter=test_learner_can_self_enroll

# Run only Feature tests
php artisan test tests/Feature

# Run only Unit tests
php artisan test tests/Unit

# Run with coverage
php artisan test --coverage

# Parallel testing
php artisan test --parallel
```

## Authorization Testing Patterns

### 403 vs 302: Know the Difference

When testing authorization failures:
- **403 Forbidden** = Policy returned `false` (correct authorization denial)
- **302 Redirect** = Validation failed or middleware redirect (wrong!)

If you expect 403 but get 302, the policy is allowing the action but something else fails.

```php
// CORRECT: Tests authorization denial
it('content manager cannot edit published course', function () {
    $cm = User::factory()->create(['role' => 'content_manager']);
    $course = Course::factory()->published()->create(['user_id' => $cm->id]);

    $this->actingAs($cm)
        ->put("/courses/{$course->id}", ['title' => 'New Title'])
        ->assertForbidden();  // ← 403, not assertRedirect()
});

// WRONG: This tests validation, not authorization
it('content manager cannot edit published course', function () {
    // ... same setup ...
    ->assertRedirect();  // ← 302 means policy allowed it, but validation failed
});
```

### Cascade Authorization Testing

When child policies delegate to parent, test both levels:

```php
describe('Published Course Content Restrictions', function () {
    beforeEach(function () {
        $this->cm = User::factory()->create(['role' => 'content_manager']);
        $this->course = Course::factory()->published()->create(['user_id' => $this->cm->id]);
        $this->section = CourseSection::factory()->create(['course_id' => $this->course->id]);
    });

    // Parent level
    it('CM cannot edit published course metadata', function () {
        $this->actingAs($this->cm)
            ->put("/courses/{$this->course->id}", ['title' => 'X'])
            ->assertForbidden();
    });

    // Child level - section (inherits from course)
    it('CM cannot add section to published course', function () {
        $this->actingAs($this->cm)
            ->post("/courses/{$this->course->id}/sections", ['title' => 'X'])
            ->assertForbidden();
    });

    // Grandchild level - lesson (inherits from course via section)
    it('CM cannot add lesson to published course', function () {
        $this->actingAs($this->cm)
            ->post("/sections/{$this->section->id}/lessons", ['title' => 'X'])
            ->assertForbidden();
    });
});
```

### Authorization Test Matrix

Always test role × state × ownership combinations:

```php
describe('Course Update Authorization Matrix', function () {
    // Admin tests (always allowed)
    it('admin can update any draft course', fn() => ...);
    it('admin can update any published course', fn() => ...);

    // CM + own course tests (state-dependent)
    it('CM can update own draft course', fn() => ...);
    it('CM cannot update own published course', fn() => ...);  // ← Key test

    // CM + other's course tests (always denied)
    it('CM cannot update other draft course', fn() => ...);
    it('CM cannot update other published course', fn() => ...);

    // Learner tests (always denied)
    it('learner cannot update any course', fn() => ...);
});
```

## Testing Services That Return Value Objects

When domain services return Result DTOs containing value objects (not Eloquent models), tests need special handling to access relationships.

### The Problem

After refactoring Result DTOs to use value objects:

```php
// EnrollmentResult now contains EnrollmentData (value object), NOT Enrollment (model)
final readonly class EnrollmentResult
{
    public function __construct(
        public EnrollmentData $enrollment,  // Value object, not model!
        public bool $isNewEnrollment,
    ) {}
}
```

Tests that previously accessed model relationships break:

```php
// ❌ BROKEN: Value objects don't have relationships
$result = $enrollmentService->enroll($dto);
$courseProgress = $result->enrollment->courseProgress();  // ERROR!
// EnrollmentData doesn't have courseProgress() method - that's on the Enrollment model
```

### The Fix: Fetch Model for Relationships

When you need relationship access, fetch the actual model using the ID from the value object:

```php
// ✅ FIXED: Fetch model to access relationships
$result = $enrollmentService->enroll($dto);

// Use value object for primitive assertions
expect($result->enrollment->userId)->toBe($user->id);
expect($result->enrollment->status)->toBe('active');

// Fetch model when you need relationships
$enrollment = LearningPathEnrollment::find($result->enrollment->id);
$courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

expect($courseProgress)->toHaveCount(2);
expect($courseProgress[0]->course_id)->toBe($course1->id);
```

### Property Naming: camelCase vs snake_case

Value objects use **camelCase** properties while Eloquent models use **snake_case**:

```php
// ❌ WRONG: snake_case on value object
expect($result->enrollment->user_id)->toBe($user->id);      // Error!
expect($result->enrollment->learning_path_id)->toBe($path->id);

// ✅ RIGHT: camelCase on value object
expect($result->enrollment->userId)->toBe($user->id);
expect($result->enrollment->learningPathId)->toBe($path->id);

// ✅ RIGHT: snake_case on Eloquent model
$enrollment = LearningPathEnrollment::find($result->enrollment->id);
expect($enrollment->user_id)->toBe($user->id);
```

### Complete Test Migration Example

Before (accessing model directly from result):
```php
it('creates enrollment with course progress', function () {
    $result = $pathEnrollmentService->enroll($learner, $path);

    // ❌ These all break with value object results
    expect($result->enrollment->user_id)->toBe($learner->id);
    expect($result->enrollment->courseProgress)->toHaveCount(2);
    expect($result->enrollment->learningPath->title)->toBe('My Path');
});
```

After (adapted for value objects):
```php
it('creates enrollment with course progress', function () {
    $result = $pathEnrollmentService->enroll($learner, $path);

    // ✅ Primitive assertions on value object (camelCase!)
    expect($result->enrollment->userId)->toBe($learner->id);
    expect($result->isNewEnrollment)->toBeTrue();

    // ✅ Fetch model for relationship assertions
    $enrollment = LearningPathEnrollment::find($result->enrollment->id);

    $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();
    expect($courseProgress)->toHaveCount(2);
    expect($courseProgress->first()->course_id)->toBe($course1->id);

    // ✅ Or use database assertions
    $this->assertDatabaseHas('learning_path_course_progress', [
        'learning_path_enrollment_id' => $result->enrollment->id,
        'course_id' => $course1->id,
    ]);
});
```

### Key Points

| Need | Approach |
|------|----------|
| Primitive values (id, userId, status) | Access directly on value object |
| Relationships (courseProgress, user) | Fetch model by ID first |
| Property names | camelCase on value objects, snake_case on models |
| Database verification | Use `assertDatabaseHas()` |

## Gotchas & Best Practices

1. **Always call `parent::setUp()`** first in setUp()
2. **Use `$this->withoutVite()`** in TestCase setUp - already done globally
3. **Factory states are chainable** - `->published()->public()->create()`
4. **Use global helpers** - `asAdmin()`, `asLearner()`, `createPublishedCourseWithContent()`
5. **describe/it pattern for unit tests** - Groups related tests
6. **Class-based for feature tests** - When you need setUp() with shared state
7. **Test policy by state and ownership** - Test all role × state × ownership combinations
8. **Use `expect()` for Pest** - More readable than PHPUnit assertions
9. **assertDatabaseHas for persistence** - Verify database state after actions
10. **Test services via contracts** - `app(ServiceContract::class)`
11. **assertForbidden() for authorization** - Not assertRedirect(), which tests validation
12. **Test cascade authorization** - If child delegates to parent, test both levels
13. **Value object results** - Fetch model by ID when testing relationships

## Creating New Tests

```bash
# Feature test
php artisan make:test --pest MyFeatureTest

# Unit test
php artisan make:test --pest --unit MyUnitTest

# Domain unit test (manual)
# Create in: tests/Unit/Domain/{Context}/...
```

## Quick Reference

```bash
# Files to reference
tests/Pest.php                                    # Global helpers
tests/TestCase.php                                # Custom assertions
tests/Feature/EnrollmentLifecycleTest.php         # Feature test example
tests/Unit/Domain/Assessment/Strategies/*         # Unit test examples
database/factories/CourseFactory.php              # Factory states example
```
