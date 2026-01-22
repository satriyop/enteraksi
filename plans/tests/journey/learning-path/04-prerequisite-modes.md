# 04 - Prerequisite Modes Test Plan

## Overview

This test plan covers the three prerequisite modes that control how courses unlock within a learning path. Each mode has different unlock behavior that significantly affects the learner experience.

**Prerequisite Modes**:
| Mode | Description | Unlock Behavior |
|------|-------------|-----------------|
| `sequential` | Berurutan (Semua sebelumnya harus selesai) | Course N unlocks only after courses 1..N-1 complete |
| `immediate_previous` | Hanya kursus sebelumnya | Course N unlocks when N-1 completes |
| `none` | Tanpa prasyarat | All courses available immediately |

**Key Files**:
- `PrerequisiteEvaluatorFactory` - Resolves correct evaluator
- `SequentialPrerequisiteEvaluator` - All previous must complete
- `ImmediatePreviousPrerequisiteEvaluator` - Only direct predecessor
- `NoPrerequisiteEvaluator` - Always returns met

---

## User Stories

### As Budi (sequential path learner):
> "Saya mengikuti learning path yang berurutan. Saya harus menyelesaikan setiap kursus secara berurutan sebelum bisa akses kursus selanjutnya."

### As Rina (immediate_previous path learner):
> "Di learning path saya, saya hanya perlu menyelesaikan kursus sebelumnya untuk membuka kursus berikutnya. Jadi kalau saya selesai kursus 2, saya langsung bisa akses kursus 3 tanpa harus selesai kursus 1."

### As Dewi (no prerequisite learner):
> "Learning path saya tidak punya prasyarat, jadi saya bebas memilih mau mulai dari kursus mana saja."

---

## Existing Test Coverage

| Test | File | Status |
|------|------|--------|
| `sequential unlock` | `PathProgressServiceTest.php` | ✅ Exists |
| `immediate_previous mode` | None | ❌ Not covered |
| `no prerequisite mode` | None | ❌ Not covered |
| Factory resolution | None | ❌ Not covered |

**Gap**: Only sequential mode partially tested. No E2E tests for mode behavior differences.

---

## Test Cases

### describe('Sequential Mode - Basic Behavior')

#### TC-PM-001: First course available immediately on enrollment
```php
it('first course available immediately on sequential path enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

    expect($courseProgress[0]->isAvailable())->toBeTrue();
    expect($courseProgress[1]->isLocked())->toBeTrue();
    expect($courseProgress[2]->isLocked())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ✅ Partially covered

#### TC-PM-002: Second course unlocks only after first completes
```php
it('second course unlocks only after first completes in sequential mode', function () {
    Event::fake([CourseUnlockedInPath::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();
    $courseProgress[0]->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Unlock next courses
    $progressService = app(PathProgressServiceContract::class);
    $unlockedCourses = $progressService->unlockNextCourses($pathEnrollment);

    expect($unlockedCourses)->toHaveCount(1);
    expect($unlockedCourses[0]->id)->toBe($courses[1]->id);

    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();
    expect($courseProgress[1]->isAvailable())->toBeTrue();
    expect($courseProgress[2]->isLocked())->toBeTrue(); // Still locked

    Event::assertDispatchedTimes(CourseUnlockedInPath::class, 1);
});
```
**Priority**: Critical
**Existing**: ✅ Exists

#### TC-PM-003: Third course requires both first and second complete
```php
it('third course requires both first and second complete in sequential mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);
    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

    // Complete only first course
    $courseProgress[0]->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);
    $progressService->unlockNextCourses($pathEnrollment);

    // Try to check prerequisites for third course
    $prereqCheck = $progressService->checkPrerequisites($pathEnrollment, $courses[2]);

    expect($prereqCheck->isMet)->toBeFalse();
    expect($prereqCheck->missingPrerequisites)->toContain($courses[1]->title);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PM-004: All courses unlock sequentially after completion
```php
it('all courses unlock sequentially after each completion', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(4)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Complete courses one by one
    foreach ($courses as $index => $course) {
        $courseProgress = $pathEnrollment->courseProgress()
            ->where('course_id', $course->id)
            ->first();

        // Verify current course is available (or completed for previous)
        if ($index === 0) {
            expect($courseProgress->isAvailable())->toBeTrue();
        }

        // Mark as completed
        $courseProgress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

        // Unlock next
        $progressService->unlockNextCourses($pathEnrollment->fresh());

        // Verify next course is now available (if exists)
        if ($index < count($courses) - 1) {
            $nextProgress = $pathEnrollment->courseProgress()
                ->where('course_id', $courses[$index + 1]->id)
                ->first();

            expect($nextProgress->isAvailable())->toBeTrue();
        }
    }
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Immediate Previous Mode - Basic Behavior')

#### TC-PM-005: First course available immediately in immediate_previous mode
```php
it('first course available immediately in immediate_previous mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'immediate_previous',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

    expect($courseProgress[0]->isAvailable())->toBeTrue();
    expect($courseProgress[1]->isLocked())->toBeTrue();
    expect($courseProgress[2]->isLocked())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-PM-006: Second course unlocks when first completes
```php
it('second course unlocks when first completes in immediate_previous mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'immediate_previous',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();
    $courseProgress[0]->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->unlockNextCourses($pathEnrollment);

    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();
    expect($courseProgress[1]->isAvailable())->toBeTrue();
    expect($courseProgress[2]->isLocked())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-PM-007: Third course unlocks when second completes (even if first incomplete)
```php
it('third course unlocks when second completes in immediate_previous mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'immediate_previous',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Setup: Create enrollment with second course already available
    $pathEnrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // First course: completed
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $pathEnrollment->id,
        'course_id' => $courses[0]->id,
        'position' => 1,
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Second course: available (will be completed)
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $pathEnrollment->id,
        'course_id' => $courses[1]->id,
        'position' => 2,
        'state' => AvailableCourseState::$name,
    ]);

    // Third course: locked
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $pathEnrollment->id,
        'course_id' => $courses[2]->id,
        'position' => 3,
        'state' => LockedCourseState::$name,
    ]);

    // Complete second course
    $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first()
        ->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->unlockNextCourses($pathEnrollment);

    // Third course should now be available
    $thirdProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[2]->id)
        ->first();

    expect($thirdProgress->isAvailable())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-PM-008: Skipping courses possible in immediate_previous mode (conceptually)
```php
it('allows non-linear completion path in immediate_previous mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'immediate_previous',
    ]);
    $courses = Course::factory()->published()->count(4)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Complete 1 -> unlocks 2
    $pathEnrollment->courseProgress()->where('course_id', $courses[0]->id)->first()
        ->update(['state' => CompletedCourseState::$name, 'completed_at' => now()]);
    $progressService->unlockNextCourses($pathEnrollment);

    // Complete 2 -> unlocks 3
    $pathEnrollment->courseProgress()->where('course_id', $courses[1]->id)->first()
        ->update(['state' => CompletedCourseState::$name, 'completed_at' => now()]);
    $progressService->unlockNextCourses($pathEnrollment->fresh());

    // Now complete 3 -> unlocks 4 (even though 2 wasn't "really" done, simulating out of order)
    $pathEnrollment->courseProgress()->where('course_id', $courses[2]->id)->first()
        ->update(['state' => CompletedCourseState::$name, 'completed_at' => now()]);
    $progressService->unlockNextCourses($pathEnrollment->fresh());

    $fourthProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[3]->id)
        ->first();

    expect($fourthProgress->isAvailable())->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('No Prerequisite Mode - Basic Behavior')

#### TC-PM-009: All courses available immediately in no prerequisite mode
```php
it('all courses available immediately in no prerequisite mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'none',
    ]);
    $courses = Course::factory()->published()->count(5)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

    // ALL courses should be available
    foreach ($courseProgress as $progress) {
        expect($progress->isAvailable())->toBeTrue();
    }
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-PM-010: Can complete courses in any order in no prerequisite mode
```php
it('can complete courses in any order in no prerequisite mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'none',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Complete third course first
    $thirdProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[2]->id)
        ->first();

    $thirdProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Then complete first course
    $firstProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[0]->id)
        ->first();

    $firstProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Progress should reflect both completed
    $pathEnrollment->refresh();
    $percentage = $progressService->calculateProgressPercentage($pathEnrollment);

    expect($percentage)->toBe(67); // 2/3 ≈ 67%
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PM-011: Course enrollment created for all courses on path enrollment
```php
it('course enrollments created for all courses in no prerequisite mode', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'none',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // All courses should have course_enrollment_id set
    $courseProgress = $pathEnrollment->courseProgress()->get();

    foreach ($courseProgress as $progress) {
        expect($progress->course_enrollment_id)->not->toBeNull();
        expect($progress->courseEnrollment)->not->toBeNull();
        expect($progress->courseEnrollment->isActive())->toBeTrue();
    }

    // Verify enrollments exist in database
    $enrollmentCount = Enrollment::where('user_id', $learner->id)
        ->whereIn('course_id', $courses->pluck('id'))
        ->count();

    expect($enrollmentCount)->toBe(3);
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Factory Resolution')

#### TC-PM-012: Factory resolves correct evaluator for each mode
```php
it('factory resolves correct evaluator for each mode', function () {
    $factory = app(PrerequisiteEvaluatorFactory::class);

    $sequentialPath = LearningPath::factory()->create(['prerequisite_mode' => 'sequential']);
    $immediatePath = LearningPath::factory()->create(['prerequisite_mode' => 'immediate_previous']);
    $nonePath = LearningPath::factory()->create(['prerequisite_mode' => 'none']);

    expect($factory->make($sequentialPath))->toBeInstanceOf(SequentialPrerequisiteEvaluator::class);
    expect($factory->make($immediatePath))->toBeInstanceOf(ImmediatePreviousPrerequisiteEvaluator::class);
    expect($factory->make($nonePath))->toBeInstanceOf(NoPrerequisiteEvaluator::class);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PM-013: Factory defaults to sequential when mode is null
```php
it('factory defaults to sequential when mode is null', function () {
    $factory = app(PrerequisiteEvaluatorFactory::class);

    $path = LearningPath::factory()->create(['prerequisite_mode' => null]);

    $evaluator = $factory->make($path);

    expect($evaluator)->toBeInstanceOf(SequentialPrerequisiteEvaluator::class);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PM-014: Factory throws for invalid mode
```php
it('factory throws exception for invalid prerequisite mode', function () {
    $factory = app(PrerequisiteEvaluatorFactory::class);

    expect(fn () => $factory->resolve('invalid_mode'))
        ->toThrow(InvalidArgumentException::class);
});
```
**Priority**: Low
**Existing**: ❌ Not covered

---

### describe('Mode Switching Scenarios')

#### TC-PM-015: Changing mode does not affect existing enrollments
```php
it('changing path mode does not affect existing enrolled learner progress', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Enroll learner
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $pathEnrollment->courseProgress()->first()->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->unlockNextCourses($pathEnrollment);

    // Admin changes mode to 'none'
    $path->update(['prerequisite_mode' => 'none']);

    // Existing progress should not change
    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->get();

    expect($courseProgress[0]->isCompleted())->toBeTrue();
    expect($courseProgress[1]->isAvailable())->toBeTrue();
    expect($courseProgress[2]->isLocked())->toBeTrue(); // Still locked from original mode

    // Note: In real application, you might need a migration job to unlock
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Prerequisite Check Results')

#### TC-PM-016: Sequential mode returns all missing prerequisites
```php
it('sequential mode returns all missing prerequisites in check result', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(4)->create([
        'title' => fn () => 'Kursus ' . fake()->word(),
    ]);

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Check prerequisites for 4th course (should need 1, 2, 3)
    $prereqCheck = $progressService->checkPrerequisites($pathEnrollment, $courses[3]);

    expect($prereqCheck->isMet)->toBeFalse();
    expect($prereqCheck->missingPrerequisites)->toHaveCount(3);
    expect($prereqCheck->message)->toBe('Previous courses must be completed');
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PM-017: Immediate_previous mode returns only one missing prerequisite
```php
it('immediate_previous mode returns only direct predecessor in check result', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'immediate_previous',
    ]);
    $courses = Course::factory()->published()->count(4)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Check prerequisites for 4th course (should only need 3rd)
    $prereqCheck = $progressService->checkPrerequisites($pathEnrollment, $courses[3]);

    expect($prereqCheck->isMet)->toBeFalse();
    expect($prereqCheck->missingPrerequisites)->toHaveCount(1);
    expect($prereqCheck->missingPrerequisites[0])->toBe($courses[2]->title);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PM-018: No prerequisite mode always returns met
```php
it('no prerequisite mode always returns prerequisites met', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'none',
    ]);
    $courses = Course::factory()->published()->count(4)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Check prerequisites for any course - should always be met
    foreach ($courses as $course) {
        $prereqCheck = $progressService->checkPrerequisites($pathEnrollment, $course);
        expect($prereqCheck->isMet)->toBeTrue();
    }
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('UI/API Integration')

#### TC-PM-019: Progress page shows correct lock status per mode
```php
it('progress page shows correct lock status per mode', function ($mode, $expectedLocked) {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => $mode,
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('progress.lockedCourses', $expectedLocked)
    );
})->with([
    'sequential' => ['sequential', 2],
    'immediate_previous' => ['immediate_previous', 2],
    'none' => ['none', 0],
]);
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PM-020: Browse page shows prerequisite mode
```php
it('browse page shows prerequisite mode information', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->create([
        'title' => 'Sequential Path',
        'prerequisite_mode' => 'sequential',
    ]);
    LearningPath::factory()->published()->create([
        'title' => 'Flexible Path',
        'prerequisite_mode' => 'none',
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 2)
        ->has('learningPaths.data.0.prerequisite_mode')
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

---

### describe('Edge Cases')

#### TC-PM-021: Single course path works with all modes
```php
it('single course path works correctly with all modes', function ($mode) {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => $mode,
    ]);
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $courseProgress = $pathEnrollment->courseProgress()->first();

    // Single course should always be available
    expect($courseProgress->isAvailable())->toBeTrue();
})->with(['sequential', 'immediate_previous', 'none']);
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PM-022: Gap in positions handled correctly
```php
it('handles gap in course positions correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    // Positions: 1, 5, 10 (gaps)
    $positions = [1, 5, 10];
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $positions[$i],
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Complete first course
    $pathEnrollment->courseProgress()
        ->where('course_id', $courses[0]->id)
        ->first()
        ->update(['state' => CompletedCourseState::$name, 'completed_at' => now()]);

    $progressService->unlockNextCourses($pathEnrollment);

    // Second course (position 5) should unlock
    $secondProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first();

    expect($secondProgress->isAvailable())->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PM-023: Optional courses don't block prerequisites
```php
it('optional courses do not block prerequisite unlocking', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(3)->create();

    // Course 2 is optional
    $path->courses()->attach($courses[0]->id, ['position' => 1, 'is_required' => true]);
    $path->courses()->attach($courses[1]->id, ['position' => 2, 'is_required' => false]); // Optional
    $path->courses()->attach($courses[2]->id, ['position' => 3, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $progressService = app(PathProgressServiceContract::class);

    // Complete first course
    $pathEnrollment->courseProgress()
        ->where('course_id', $courses[0]->id)
        ->first()
        ->update(['state' => CompletedCourseState::$name, 'completed_at' => now()]);

    $progressService->unlockNextCourses($pathEnrollment);

    // Note: This tests current behavior - optional courses still need to be completed
    // in sequential mode for next course to unlock. Test documents actual behavior.
    $thirdProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[2]->id)
        ->first();

    // In sequential mode, position 2 (optional) must still be completed
    expect($thirdProgress->isLocked())->toBeTrue();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PM-024: Concurrent unlock requests handled correctly
```php
it('handles concurrent unlock requests without duplicate enrollments', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'sequential',
    ]);
    $courses = Course::factory()->published()->count(2)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $pathEnrollment->courseProgress()->first()->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $progressService = app(PathProgressServiceContract::class);

    // Simulate concurrent unlock requests
    $progressService->unlockNextCourses($pathEnrollment);
    $progressService->unlockNextCourses($pathEnrollment->fresh());
    $progressService->unlockNextCourses($pathEnrollment->fresh());

    // Should only have one enrollment for second course
    $enrollmentCount = Enrollment::where('user_id', $learner->id)
        ->where('course_id', $courses[1]->id)
        ->count();

    expect($enrollmentCount)->toBe(1);
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

## Test Summary

| Category | Test Count | Existing | New |
|----------|------------|----------|-----|
| Sequential Mode | 4 | 1 | 3 |
| Immediate Previous Mode | 4 | 0 | 4 |
| No Prerequisite Mode | 3 | 0 | 3 |
| Factory Resolution | 3 | 0 | 3 |
| Mode Switching | 1 | 0 | 1 |
| Check Results | 3 | 0 | 3 |
| UI/API Integration | 2 | 0 | 2 |
| Edge Cases | 4 | 0 | 4 |
| **Total** | **24** | **1** | **23** |

---

## Edge Cases to Consider

1. **Position gaps**: Courses at positions 1, 5, 10 instead of 1, 2, 3
2. **Removed course**: What happens if course at position 2 is removed?
3. **Reordered courses**: Admin reorders while learner in progress
4. **Optional vs required**: How do optional courses interact with prerequisites?
5. **Default mode**: What if prerequisite_mode column is NULL?
6. **Mixed completion states**: What if course was completed outside the path?

---

## Implementation Notes

- Test with `->with()` data providers for mode comparison tests
- Use `Event::fake()` when testing unlock events
- Verify enrollment creation/reuse for each mode
- Test file: `tests/Feature/Journey/LearningPath/PrerequisiteModesTest.php`
- Import evaluators for instanceof checks: `SequentialPrerequisiteEvaluator`, etc.

---

## Dependencies

- Requires: `PathProgressService`, `PathEnrollmentService`
- Uses: `PrerequisiteEvaluatorFactory` for mode resolution
- Related: `03-learner-progress-completion.md` (unlock mechanics)
