# Phase 6: Test Plan

> **Phase**: 6 of 6
> **Scope**: Comprehensive test coverage for Learning Paths Enhancement

---

## Test Overview

| Category | Test Count | Priority |
|----------|------------|----------|
| Unit Tests - Services | ~25 | High |
| Unit Tests - Models | ~15 | High |
| Feature Tests - Enrollment | ~20 | High |
| Feature Tests - Progress | ~15 | High |
| Feature Tests - Prerequisites | ~12 | High |
| Feature Tests - Learner UI | ~10 | Medium |
| Feature Tests - Auto-Enrollment | ~8 | Low |
| Edge Cases | ~10 | Medium |
| Integration Tests | ~5 | High |
| **Total** | **~120** | |

---

## 6.1 Unit Tests - PathEnrollmentService

### File: `tests/Unit/Domain/LearningPath/Services/PathEnrollmentServiceTest.php`

```php
<?php

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use App\Domain\LearningPath\Events\PathDropped;
use App\Domain\LearningPath\Exceptions\AlreadyEnrolledInPathException;
use App\Domain\LearningPath\Exceptions\PathNotPublishedException;
use App\Domain\LearningPath\Services\PathEnrollmentService;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->progressService = Mockery::mock(PathProgressServiceContract::class);
    $this->service = new PathEnrollmentService($this->progressService);
});

describe('enroll', function () {
    it('creates enrollment for valid path and user', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);

        $enrollment = $this->service->enroll($user, $path);

        expect($enrollment)->toBeInstanceOf(LearningPathEnrollment::class);
        expect($enrollment->user_id)->toBe($user->id);
        expect($enrollment->learning_path_id)->toBe($path->id);
        expect($enrollment->status)->toBe('active');
        expect($enrollment->progress_percentage)->toBe(0);
    });

    it('dispatches PathEnrollmentCreated event', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);

        $this->service->enroll($user, $path);

        Event::assertDispatched(PathEnrollmentCreated::class);
    });

    it('throws exception for unpublished path', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->unpublished()->create();

        $this->service->enroll($user, $path);
    })->throws(PathNotPublishedException::class);

    it('throws exception when already enrolled', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);

        $this->service->enroll($user, $path);
        $this->service->enroll($user, $path); // Second attempt
    })->throws(AlreadyEnrolledInPathException::class);

    it('initializes course progress for all courses', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->count(3)->published()->create();
        foreach ($courses as $index => $course) {
            $path->courses()->attach($course->id, ['position' => $index]);
        }

        $enrollment = $this->service->enroll($user, $path);

        expect($enrollment->courseProgress)->toHaveCount(3);
        expect($enrollment->courseProgress->first()->status)->toBe('available');
        expect($enrollment->courseProgress->skip(1)->first()->status)->toBe('locked');
    });
});

describe('canEnroll', function () {
    it('returns true for eligible user and published path', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->published()->create();

        expect($this->service->canEnroll($user, $path))->toBeTrue();
    });

    it('returns false for unpublished path', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->unpublished()->create();

        expect($this->service->canEnroll($user, $path))->toBeFalse();
    });

    it('returns false when already enrolled', function () {
        $user = User::factory()->create();
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);

        $this->service->enroll($user, $path);

        expect($this->service->canEnroll($user, $path))->toBeFalse();
    });
});

describe('drop', function () {
    it('marks enrollment as dropped', function () {
        $enrollment = LearningPathEnrollment::factory()->create();

        $this->service->drop($enrollment, 'Test reason');

        expect($enrollment->fresh()->status)->toBe('dropped');
        expect($enrollment->fresh()->dropped_at)->not->toBeNull();
    });

    it('dispatches PathDropped event', function () {
        $enrollment = LearningPathEnrollment::factory()->create();

        $this->service->drop($enrollment);

        Event::assertDispatched(PathDropped::class);
    });

    it('stores drop reason in metadata', function () {
        $enrollment = LearningPathEnrollment::factory()->create();

        $this->service->drop($enrollment, 'Changed my mind');

        expect($enrollment->fresh()->metadata['drop_reason'])->toBe('Changed my mind');
    });
});

describe('getActiveEnrollments', function () {
    it('returns only active enrollments for user', function () {
        $user = User::factory()->create();
        LearningPathEnrollment::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'active']);
        LearningPathEnrollment::factory()->completed()->create(['user_id' => $user->id]);
        LearningPathEnrollment::factory()->dropped()->create(['user_id' => $user->id]);

        $enrollments = $this->service->getActiveEnrollments($user);

        expect($enrollments)->toHaveCount(2);
        expect($enrollments->every(fn ($e) => $e->status === 'active'))->toBeTrue();
    });
});

describe('complete', function () {
    it('marks enrollment as completed', function () {
        $enrollment = LearningPathEnrollment::factory()->create(['status' => 'active']);

        $this->service->complete($enrollment);

        expect($enrollment->fresh()->status)->toBe('completed');
        expect($enrollment->fresh()->completed_at)->not->toBeNull();
        expect($enrollment->fresh()->progress_percentage)->toBe(100);
    });
});
```

---

## 6.2 Unit Tests - PathProgressService

### File: `tests/Unit/Domain/LearningPath/Services/PathProgressServiceTest.php`

```php
<?php

use App\Domain\LearningPath\DTOs\PathProgressDTO;
use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Domain\LearningPath\Services\PathProgressService;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;

beforeEach(function () {
    $this->service = new PathProgressService();
});

describe('calculateProgressPercentage', function () {
    it('returns 0 for no completed courses', function () {
        $enrollment = createEnrollmentWithCourses(3, 0);

        expect($this->service->calculateProgressPercentage($enrollment))->toBe(0);
    });

    it('returns 100 for all courses completed', function () {
        $enrollment = createEnrollmentWithCourses(3, 3);

        expect($this->service->calculateProgressPercentage($enrollment))->toBe(100);
    });

    it('returns correct percentage for partial completion', function () {
        $enrollment = createEnrollmentWithCourses(4, 2);

        expect($this->service->calculateProgressPercentage($enrollment))->toBe(50);
    });

    it('weights optional courses less', function () {
        $enrollment = createEnrollmentWithMixedCourses();
        // 2 required + 1 optional, only 1 required completed
        // Weight: required=1, optional=0.5
        // Total weight: 2.5, Completed weight: 1
        // Expected: 40%

        expect($this->service->calculateProgressPercentage($enrollment))->toBe(40);
    });
});

describe('checkPrerequisites', function () {
    it('returns met for first course', function () {
        $enrollment = createEnrollmentWithCourses(3, 0);
        $firstCourse = $enrollment->learningPath->courses->first();

        $result = $this->service->checkPrerequisites($enrollment, $firstCourse);

        expect($result->isMet)->toBeTrue();
    });

    it('returns not met when previous course not completed', function () {
        $enrollment = createEnrollmentWithCourses(3, 0);
        $secondCourse = $enrollment->learningPath->courses->skip(1)->first();

        $result = $this->service->checkPrerequisites($enrollment, $secondCourse);

        expect($result->isMet)->toBeFalse();
        expect($result->missingPrerequisites)->toHaveCount(1);
    });

    it('returns met when previous course completed', function () {
        $enrollment = createEnrollmentWithCourses(3, 1);
        $secondCourse = $enrollment->learningPath->courses->skip(1)->first();

        $result = $this->service->checkPrerequisites($enrollment, $secondCourse);

        expect($result->isMet)->toBeTrue();
    });
});

describe('isCourseUnlocked', function () {
    it('returns true for available course', function () {
        $enrollment = createEnrollmentWithCourses(3, 0);
        $firstCourse = $enrollment->learningPath->courses->first();

        expect($this->service->isCourseUnlocked($enrollment, $firstCourse))->toBeTrue();
    });

    it('returns false for locked course', function () {
        $enrollment = createEnrollmentWithCourses(3, 0);
        $lastCourse = $enrollment->learningPath->courses->last();

        expect($this->service->isCourseUnlocked($enrollment, $lastCourse))->toBeFalse();
    });
});

describe('unlockNextCourses', function () {
    it('unlocks next course when prerequisites met', function () {
        $enrollment = createEnrollmentWithCourses(3, 1);

        $unlocked = $this->service->unlockNextCourses($enrollment);

        expect($unlocked)->toHaveCount(1);
    });

    it('returns empty array when no courses can be unlocked', function () {
        $enrollment = createEnrollmentWithCourses(3, 0);

        $unlocked = $this->service->unlockNextCourses($enrollment);

        expect($unlocked)->toHaveCount(0);
    });
});

describe('isPathCompleted', function () {
    it('returns true when all required courses completed', function () {
        $enrollment = createEnrollmentWithCourses(3, 3);

        expect($this->service->isPathCompleted($enrollment))->toBeTrue();
    });

    it('returns false when required courses not completed', function () {
        $enrollment = createEnrollmentWithCourses(3, 2);

        expect($this->service->isPathCompleted($enrollment))->toBeFalse();
    });

    it('returns true when only optional courses remain', function () {
        $enrollment = createEnrollmentWithOnlyOptionalRemaining();

        expect($this->service->isPathCompleted($enrollment))->toBeTrue();
    });
});

describe('getProgress', function () {
    it('returns PathProgressDTO with correct data', function () {
        $enrollment = createEnrollmentWithCourses(3, 1);

        $progress = $this->service->getProgress($enrollment);

        expect($progress)->toBeInstanceOf(PathProgressDTO::class);
        expect($progress->totalCourses)->toBe(3);
        expect($progress->completedCourses)->toBe(1);
    });
});

// Helper functions
function createEnrollmentWithCourses(int $total, int $completed): LearningPathEnrollment
{
    $path = LearningPath::factory()->published()->create();
    $enrollment = LearningPathEnrollment::factory()->create([
        'learning_path_id' => $path->id,
    ]);

    for ($i = 0; $i < $total; $i++) {
        $course = Course::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => $i, 'is_required' => true]);

        $status = $i < $completed ? 'completed' : ($i === $completed ? 'available' : 'locked');
        LearningPathCourseProgress::factory()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'status' => $status,
            'completion_percentage' => $status === 'completed' ? 100 : 0,
        ]);
    }

    return $enrollment->fresh()->load(['learningPath.courses', 'courseProgress']);
}
```

---

## 6.3 Unit Tests - PrerequisiteEvaluator

### File: `tests/Unit/Domain/LearningPath/Services/PrerequisiteEvaluatorTest.php`

```php
<?php

use App\Domain\LearningPath\DTOs\PrerequisiteCheckResult;
use App\Domain\LearningPath\Services\PrerequisiteEvaluator;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;

beforeEach(function () {
    $this->evaluator = new PrerequisiteEvaluator();
});

describe('sequential prerequisites', function () {
    it('first course always passes', function () {
        $enrollment = createSequentialPath(3, 0);
        $course = $enrollment->learningPath->courses->first();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeTrue();
    });

    it('second course requires first to be completed', function () {
        $enrollment = createSequentialPath(3, 0);
        $course = $enrollment->learningPath->courses->skip(1)->first();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeFalse();
        expect($result->reason)->toContain('Selesaikan kursus');
    });

    it('passes when previous course completed', function () {
        $enrollment = createSequentialPath(3, 1);
        $course = $enrollment->learningPath->courses->skip(1)->first();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeTrue();
    });
});

describe('course-based prerequisites (all operator)', function () {
    it('fails when not all required courses completed', function () {
        $enrollment = createPathWithExplicitPrereqs('all', completed: 1, required: 2);
        $course = $enrollment->learningPath->courses->last();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeFalse();
        expect($result->missingPrerequisites)->toHaveCount(1);
    });

    it('passes when all required courses completed', function () {
        $enrollment = createPathWithExplicitPrereqs('all', completed: 2, required: 2);
        $course = $enrollment->learningPath->courses->last();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeTrue();
    });
});

describe('course-based prerequisites (any operator)', function () {
    it('passes when any required course completed', function () {
        $enrollment = createPathWithExplicitPrereqs('any', completed: 1, required: 3);
        $course = $enrollment->learningPath->courses->last();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeTrue();
    });

    it('fails when no required courses completed', function () {
        $enrollment = createPathWithExplicitPrereqs('any', completed: 0, required: 3);
        $course = $enrollment->learningPath->courses->last();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeFalse();
    });
});

describe('minimum completion percentage', function () {
    it('fails when completion below minimum', function () {
        $enrollment = createPathWithMinCompletion(minPercent: 80, actualPercent: 60);
        $course = $enrollment->learningPath->courses->skip(1)->first();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeFalse();
        expect($result->reason)->toContain('80%');
    });

    it('passes when completion meets minimum', function () {
        $enrollment = createPathWithMinCompletion(minPercent: 80, actualPercent: 85);
        $course = $enrollment->learningPath->courses->skip(1)->first();

        $result = $this->evaluator->evaluate($enrollment, $course);

        expect($result->isMet)->toBeTrue();
    });
});
```

---

## 6.4 Feature Tests - Path Enrollment Flow

### File: `tests/Feature/LearningPath/PathEnrollmentTest.php`

```php
<?php

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;

beforeEach(function () {
    $this->learner = User::factory()->create(['role' => 'learner']);
    $this->path = LearningPath::factory()->published()->create();
    $this->courses = Course::factory()->count(3)->published()->create();
    foreach ($this->courses as $index => $course) {
        $this->path->courses()->attach($course->id, ['position' => $index]);
    }
});

describe('enrollment via UI', function () {
    it('learner can enroll in published path', function () {
        $this->actingAs($this->learner)
            ->post(route('learner.paths.enroll', $this->path))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('learning_path_enrollments', [
            'user_id' => $this->learner->id,
            'learning_path_id' => $this->path->id,
            'status' => 'active',
        ]);
    });

    it('learner cannot enroll in unpublished path', function () {
        $unpublished = LearningPath::factory()->unpublished()->create();

        $this->actingAs($this->learner)
            ->post(route('learner.paths.enroll', $unpublished))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('learning_path_enrollments', [
            'user_id' => $this->learner->id,
            'learning_path_id' => $unpublished->id,
        ]);
    });

    it('learner cannot enroll twice in same path', function () {
        $this->actingAs($this->learner)
            ->post(route('learner.paths.enroll', $this->path));

        $this->actingAs($this->learner)
            ->post(route('learner.paths.enroll', $this->path))
            ->assertRedirect()
            ->assertSessionHas('info');

        expect(LearningPathEnrollment::where('user_id', $this->learner->id)
            ->where('learning_path_id', $this->path->id)
            ->count())->toBe(1);
    });
});

describe('drop from path', function () {
    it('learner can drop from active enrollment', function () {
        $enrollment = LearningPathEnrollment::factory()->create([
            'user_id' => $this->learner->id,
            'learning_path_id' => $this->path->id,
        ]);

        $this->actingAs($this->learner)
            ->delete(route('learner.paths.drop', $this->path))
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($enrollment->fresh()->status)->toBe('dropped');
    });

    it('learner cannot drop if not enrolled', function () {
        $this->actingAs($this->learner)
            ->delete(route('learner.paths.drop', $this->path))
            ->assertRedirect()
            ->assertSessionHas('error');
    });
});

describe('my learning paths page', function () {
    it('shows enrolled paths', function () {
        LearningPathEnrollment::factory()->count(2)->create([
            'user_id' => $this->learner->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->learner)
            ->get(route('learner.paths.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('learner/paths/Index')
                ->has('activePaths', 2)
            );
    });

    it('separates active and completed paths', function () {
        LearningPathEnrollment::factory()->create([
            'user_id' => $this->learner->id,
            'status' => 'active',
        ]);
        LearningPathEnrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
        ]);

        $this->actingAs($this->learner)
            ->get(route('learner.paths.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('activePaths', 1)
                ->has('completedPaths', 1)
            );
    });
});
```

---

## 6.5 Feature Tests - Progress and Prerequisites

### File: `tests/Feature/LearningPath/PathProgressTest.php`

```php
<?php

use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use App\Models\User;

beforeEach(function () {
    $this->progressService = app(PathProgressServiceContract::class);
});

describe('progress calculation', function () {
    it('calculates correct percentage after course completion', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 4, completed: 2);

        expect($enrollment->fresh()->progress_percentage)->toBe(50);
    });

    it('updates progress when course is completed', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 4, completed: 1);
        $secondCourse = $enrollment->courseProgress->skip(1)->first();

        // Simulate completing second course
        $secondCourse->update(['status' => 'completed', 'completion_percentage' => 100]);

        $newPercentage = $this->progressService->calculateProgressPercentage($enrollment->fresh());

        expect($newPercentage)->toBe(50);
    });
});

describe('course unlocking', function () {
    it('first course is available on enrollment', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 3, completed: 0);

        $firstProgress = $enrollment->courseProgress->first();

        expect($firstProgress->status)->toBe('available');
    });

    it('subsequent courses are locked initially', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 3, completed: 0);

        $lockedCourses = $enrollment->courseProgress->where('status', 'locked');

        expect($lockedCourses)->toHaveCount(2);
    });

    it('next course unlocks when previous is completed', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 3, completed: 0);

        // Complete first course
        $firstProgress = $enrollment->courseProgress->first();
        $firstProgress->update(['status' => 'completed', 'completion_percentage' => 100]);

        // Unlock next courses
        $this->progressService->unlockNextCourses($enrollment->fresh());

        $secondProgress = $enrollment->fresh()->courseProgress->skip(1)->first();
        expect($secondProgress->status)->toBe('available');
    });
});

describe('path completion', function () {
    it('path is marked completed when all required courses done', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 3, completed: 2);

        // Complete last course
        $lastProgress = $enrollment->courseProgress->last();
        $lastProgress->update(['status' => 'completed', 'completion_percentage' => 100]);

        expect($this->progressService->isPathCompleted($enrollment->fresh()))->toBeTrue();
    });

    it('path is not completed with remaining required courses', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 3, completed: 1);

        expect($this->progressService->isPathCompleted($enrollment))->toBeFalse();
    });
});

// Helper
function createPathEnrollmentWithProgress(int $total, int $completed): LearningPathEnrollment
{
    $path = LearningPath::factory()->published()->create();
    $user = User::factory()->create();
    $enrollment = LearningPathEnrollment::factory()->create([
        'learning_path_id' => $path->id,
        'user_id' => $user->id,
        'progress_percentage' => (int)(($completed / $total) * 100),
    ]);

    for ($i = 0; $i < $total; $i++) {
        $course = Course::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => $i, 'is_required' => true]);

        $status = match(true) {
            $i < $completed => 'completed',
            $i === $completed => 'available',
            default => 'locked',
        };

        LearningPathCourseProgress::factory()->create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'status' => $status,
            'completion_percentage' => $status === 'completed' ? 100 : 0,
            'unlocked_at' => in_array($status, ['available', 'in_progress', 'completed']) ? now() : null,
        ]);
    }

    return $enrollment->fresh()->load(['learningPath.courses', 'courseProgress']);
}
```

---

## 6.6 Feature Tests - Auto-Enrollment

### File: `tests/Feature/LearningPath/AutoEnrollmentTest.php`

```php
<?php

use App\Domain\LearningPath\Services\AutoEnrollmentService;
use App\Models\LearningPath;
use App\Models\LearningPathAutoEnrollmentRule;
use App\Models\LearningPathEnrollment;
use App\Models\User;

beforeEach(function () {
    $this->service = app(AutoEnrollmentService::class);
});

describe('role-based auto-enrollment', function () {
    it('enrolls user with matching role', function () {
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);

        $rule = LearningPathAutoEnrollmentRule::factory()->create([
            'learning_path_id' => $path->id,
            'rule_type' => 'role',
            'conditions' => ['roles' => ['teller']],
            'trigger_type' => 'on_create',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'teller']);

        $enrolled = $this->service->processForUser($user, 'on_create');

        expect($enrolled)->toHaveCount(1);
        expect(LearningPathEnrollment::where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('does not enroll user with non-matching role', function () {
        $path = LearningPath::factory()->published()->create();

        LearningPathAutoEnrollmentRule::factory()->create([
            'learning_path_id' => $path->id,
            'rule_type' => 'role',
            'conditions' => ['roles' => ['teller']],
        ]);

        $user = User::factory()->create(['role' => 'learner']);

        $enrolled = $this->service->processForUser($user, 'on_create');

        expect($enrolled)->toBeEmpty();
    });
});

describe('rule matching', function () {
    it('matches combined role and department conditions', function () {
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);

        $rule = LearningPathAutoEnrollmentRule::factory()->create([
            'learning_path_id' => $path->id,
            'rule_type' => 'combined',
            'conditions' => [
                'roles' => ['teller', 'customer_service'],
                'departments' => ['branch_a'],
            ],
        ]);

        $matchingUser = User::factory()->create([
            'role' => 'teller',
            'department' => 'branch_a',
        ]);

        $nonMatchingUser = User::factory()->create([
            'role' => 'teller',
            'department' => 'branch_b',
        ]);

        expect($rule->matchesUser($matchingUser))->toBeTrue();
        expect($rule->matchesUser($nonMatchingUser))->toBeFalse();
    });
});

describe('inactive rules', function () {
    it('skips inactive rules', function () {
        $path = LearningPath::factory()->published()->create();

        LearningPathAutoEnrollmentRule::factory()->create([
            'learning_path_id' => $path->id,
            'rule_type' => 'all_new_users',
            'is_active' => false,
        ]);

        $user = User::factory()->create();

        $enrolled = $this->service->processForUser($user, 'on_create');

        expect($enrolled)->toBeEmpty();
    });
});
```

---

## 6.7 Edge Cases

### File: `tests/Feature/LearningPath/EdgeCasesTest.php`

```php
<?php

describe('edge cases', function () {
    it('handles path with no courses gracefully', function () {
        $path = LearningPath::factory()->published()->create();
        $user = User::factory()->create();

        $service = app(PathEnrollmentServiceContract::class);
        $enrollment = $service->enroll($user, $path);

        expect($enrollment->courseProgress)->toHaveCount(0);
        expect($enrollment->progress_percentage)->toBe(0);
    });

    it('handles concurrent enrollment attempts', function () {
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);
        $user = User::factory()->create();

        $service = app(PathEnrollmentServiceContract::class);

        // Simulate concurrent requests
        $results = collect(range(1, 5))->map(function () use ($service, $user, $path) {
            try {
                return $service->enroll($user, $path);
            } catch (AlreadyEnrolledInPathException $e) {
                return null;
            }
        })->filter();

        expect($results)->toHaveCount(1);
        expect(LearningPathEnrollment::where('user_id', $user->id)->count())->toBe(1);
    });

    it('handles deleted course in path', function () {
        $enrollment = createPathEnrollmentWithProgress(total: 3, completed: 1);
        $secondCourse = $enrollment->learningPath->courses->skip(1)->first();

        // Delete the course
        $secondCourse->delete();

        $progressService = app(PathProgressServiceContract::class);

        // Should not throw error
        $progress = $progressService->getProgress($enrollment->fresh());

        expect($progress)->toBeInstanceOf(PathProgressDTO::class);
    });

    it('handles user deletion with active enrollment', function () {
        $enrollment = LearningPathEnrollment::factory()->create();
        $userId = $enrollment->user_id;

        // Delete user
        $enrollment->user->delete();

        // Enrollment should be cascade deleted
        expect(LearningPathEnrollment::where('user_id', $userId)->exists())->toBeFalse();
    });

    it('handles path unpublishing with active enrollments', function () {
        $path = LearningPath::factory()->published()->create();
        $enrollment = LearningPathEnrollment::factory()->create([
            'learning_path_id' => $path->id,
        ]);

        // Unpublish path
        $path->update(['is_published' => false]);

        // Existing enrollment should still be accessible
        expect($enrollment->fresh())->not->toBeNull();
        expect($enrollment->fresh()->status)->toBe('active');
    });

    it('calculates progress correctly with 0% minimum completion', function () {
        // Edge case: min_completion_percentage set to 0 or null
        $enrollment = createPathEnrollmentWithProgress(total: 2, completed: 1);

        $progressService = app(PathProgressServiceContract::class);
        $percentage = $progressService->calculateProgressPercentage($enrollment);

        expect($percentage)->toBe(50);
    });

    it('handles re-enrollment after dropping', function () {
        $path = LearningPath::factory()->published()->create();
        $path->courses()->attach(Course::factory()->published()->create()->id);
        $user = User::factory()->create();

        $service = app(PathEnrollmentServiceContract::class);

        // Enroll
        $enrollment1 = $service->enroll($user, $path);

        // Drop
        $service->drop($enrollment1);

        // Re-enroll should work
        $enrollment2 = $service->enroll($user, $path);

        expect($enrollment2->id)->not->toBe($enrollment1->id);
        expect($enrollment2->status)->toBe('active');
    });
});
```

---

## 6.8 Integration Tests

### File: `tests/Feature/LearningPath/PathLifecycleIntegrationTest.php`

```php
<?php

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Domain\LearningPath\Events\PathCompleted;
use App\Domain\LearningPath\Events\PathEnrollmentCreated;
use Illuminate\Support\Facades\Event;

describe('full learning path lifecycle', function () {
    it('completes full path journey from enrollment to completion', function () {
        Event::fake([PathEnrollmentCreated::class, PathCompleted::class]);

        // Setup
        $user = User::factory()->create(['role' => 'learner']);
        $path = LearningPath::factory()->published()->create();
        $courses = Course::factory()->count(3)->published()->create();
        foreach ($courses as $index => $course) {
            $path->courses()->attach($course->id, ['position' => $index, 'is_required' => true]);
        }

        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $progressService = app(PathProgressServiceContract::class);

        // 1. Enroll in path
        $pathEnrollment = $enrollmentService->enroll($user, $path);
        Event::assertDispatched(PathEnrollmentCreated::class);

        expect($pathEnrollment->progress_percentage)->toBe(0);
        expect($pathEnrollment->courseProgress->where('status', 'available'))->toHaveCount(1);

        // 2. Complete first course
        $firstProgress = $pathEnrollment->courseProgress->first();
        $firstProgress->update(['status' => 'completed', 'completion_percentage' => 100]);
        $progressService->unlockNextCourses($pathEnrollment->fresh());

        $pathEnrollment->refresh();
        expect($pathEnrollment->courseProgress->where('status', 'available'))->toHaveCount(1);

        // 3. Complete second course
        $secondProgress = $pathEnrollment->courseProgress->skip(1)->first();
        $secondProgress->update(['status' => 'completed', 'completion_percentage' => 100]);
        $progressService->unlockNextCourses($pathEnrollment->fresh());

        // 4. Complete third course
        $thirdProgress = $pathEnrollment->fresh()->courseProgress->skip(2)->first();
        $thirdProgress->update(['status' => 'completed', 'completion_percentage' => 100]);

        // 5. Check completion
        expect($progressService->isPathCompleted($pathEnrollment->fresh()))->toBeTrue();

        // 6. Mark path as completed
        $enrollmentService->complete($pathEnrollment->fresh());
        Event::assertDispatched(PathCompleted::class);

        expect($pathEnrollment->fresh()->status)->toBe('completed');
        expect($pathEnrollment->fresh()->progress_percentage)->toBe(100);
    });
});
```

---

## 6.9 Test Utilities

### File: `tests/Helpers/LearningPathTestHelpers.php`

```php
<?php

namespace Tests\Helpers;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use App\Models\User;

trait LearningPathTestHelpers
{
    protected function createPathWithCourses(int $courseCount, bool $published = true): LearningPath
    {
        $path = LearningPath::factory()->{$published ? 'published' : 'unpublished'}()->create();

        for ($i = 0; $i < $courseCount; $i++) {
            $course = Course::factory()->published()->create();
            $path->courses()->attach($course->id, [
                'position' => $i,
                'is_required' => true,
            ]);
        }

        return $path->fresh()->load('courses');
    }

    protected function createEnrollmentWithProgress(
        User $user,
        LearningPath $path,
        int $completedCourses = 0
    ): LearningPathEnrollment {
        $enrollment = LearningPathEnrollment::factory()->create([
            'user_id' => $user->id,
            'learning_path_id' => $path->id,
        ]);

        foreach ($path->courses as $index => $course) {
            $status = match(true) {
                $index < $completedCourses => 'completed',
                $index === $completedCourses => 'available',
                default => 'locked',
            };

            LearningPathCourseProgress::factory()->create([
                'learning_path_enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'status' => $status,
                'completion_percentage' => $status === 'completed' ? 100 : 0,
            ]);
        }

        return $enrollment->fresh()->load('courseProgress');
    }
}
```

---

## Implementation Checklist

- [ ] Create unit tests for PathEnrollmentService
- [ ] Create unit tests for PathProgressService
- [ ] Create unit tests for PrerequisiteEvaluator
- [ ] Create feature tests for enrollment flow
- [ ] Create feature tests for progress tracking
- [ ] Create feature tests for auto-enrollment
- [ ] Create edge case tests
- [ ] Create integration test for full lifecycle
- [ ] Create test helpers
- [ ] Ensure all tests pass
- [ ] Check test coverage (target >90%)

---

## Running Tests

```bash
# Run all learning path tests
php artisan test --filter=LearningPath

# Run specific test file
php artisan test tests/Unit/Domain/LearningPath/Services/PathEnrollmentServiceTest.php

# Run with coverage
php artisan test --filter=LearningPath --coverage

# Run only unit tests
php artisan test tests/Unit/Domain/LearningPath

# Run only feature tests
php artisan test tests/Feature/LearningPath
```

---

## Test Coverage Goals

| Component | Target Coverage |
|-----------|-----------------|
| PathEnrollmentService | 95% |
| PathProgressService | 95% |
| PrerequisiteEvaluator | 90% |
| AutoEnrollmentService | 85% |
| Models | 90% |
| Controllers | 85% |

---

## Summary

This comprehensive test plan covers:
- **~120 test cases** across unit, feature, edge case, and integration tests
- All user stories from US-01 to US-12
- Core functionality: enrollment, progress, prerequisites, unlocking
- Edge cases: concurrent access, deleted resources, re-enrollment
- Full lifecycle integration test
- Test helpers for efficient test writing

The tests ensure the Learning Paths Enhancement feature works correctly, handles edge cases gracefully, and maintains data integrity throughout the learning journey.
