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
