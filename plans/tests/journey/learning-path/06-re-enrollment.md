# 06 - Re-Enrollment Journey Test Plan

## Overview

This test plan covers the re-enrollment scenarios when a learner who previously dropped a learning path wants to enroll again. The system supports two modes: resetting progress (fresh start) or preserving previous progress.

**Re-enrollment Behavior**:
- `preserveProgress = false` (default): Delete old progress, start fresh
- `preserveProgress = true`: Keep previous course progress, re-link enrollments

**Key Service Methods**:
- `PathEnrollmentService::enroll()` - Detects dropped enrollment automatically
- `PathEnrollmentService::reactivateEnrollment()` - Handles reactivation logic
- `PathEnrollmentService::getDroppedEnrollment()` - Finds dropped enrollment

---

## User Stories

### As Dewi (re-enrolling learner):
> "Saya pernah keluar dari learning path karena sibuk. Sekarang saya ingin mendaftar lagi dan memulai dari awal."

### As Andi (returning learner with progress):
> "Saya pernah keluar dari learning path tapi sudah selesai 2 dari 5 kursus. Saya ingin melanjutkan dari progress terakhir saya."

### As Siti (completed then dropped):
> "Saya sudah menyelesaikan learning path, tapi kemudian di-drop karena masalah teknis. Saya ingin status saya dipulihkan."

---

## Existing Test Coverage

| Test | File | Status |
|------|------|--------|
| `reactivateEnrollment` | `PathEnrollmentServiceTest.php` | ✅ Exists (unit) |
| `getDroppedEnrollment` | `PathEnrollmentServiceTest.php` | ⚠️ Partial |

**Gap**: No E2E HTTP tests for re-enrollment. No UI flow tests.

---

## Test Cases

### describe('Re-enrollment Detection')

#### TC-RE-001: System detects dropped enrollment when enrolling
```php
it('system detects dropped enrollment when enrolling', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create dropped enrollment
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 50,
        'dropped_at' => now()->subDays(30),
        'drop_reason' => 'Sibuk dengan pekerjaan',
    ]);

    // Enroll again
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Should reuse the same enrollment record
    expect($result->enrollment->id)->toBe($droppedEnrollment->id);
    expect($result->isNewEnrollment)->toBeFalse();
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit test exists

#### TC-RE-002: New enrollment created when no dropped enrollment exists
```php
it('new enrollment created when no dropped enrollment exists', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll for the first time
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    expect($result->isNewEnrollment)->toBeTrue();
    expect($result->enrollment)->not->toBeNull();
});
```
**Priority**: High
**Existing**: ✅ Covered

---

### describe('Re-enrollment with Progress Reset (Default)')

#### TC-RE-003: Progress reset on re-enrollment by default
```php
it('progress reset on re-enrollment by default', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Create dropped enrollment with progress
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 67, // 2 of 3 complete
    ]);

    // Add old course progress
    foreach ($courses as $i => $course) {
        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $course->id,
            'position' => $i + 1,
            'state' => $i < 2 ? CompletedCourseState::$name : LockedCourseState::$name,
            'completed_at' => $i < 2 ? now()->subDays(30) : null,
        ]);
    }

    // Re-enroll (without preserve)
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Progress should be reset
    expect($result->enrollment->progress_percentage)->toBe(0);

    // Old course progress should be replaced
    $courseProgress = $result->enrollment->courseProgress()->orderBy('position')->get();
    expect($courseProgress)->toHaveCount(3);
    expect($courseProgress[0]->isAvailable())->toBeTrue();
    expect($courseProgress[1]->isLocked())->toBeTrue();
    expect($courseProgress[2]->isLocked())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit test exists

#### TC-RE-004: New course enrollments created on reset
```php
it('new course enrollments created on progress reset', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create dropped enrollment
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $oldCourseEnrollment = Enrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $course->id,
        'course_enrollment_id' => $oldCourseEnrollment->id,
        'position' => 1,
        'state' => AvailableCourseState::$name,
    ]);

    // Re-enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Should have new course enrollment
    $courseProgress = $result->enrollment->courseProgress()->first();
    expect($courseProgress->course_enrollment_id)->not->toBe($oldCourseEnrollment->id);
    expect($courseProgress->courseEnrollment->isActive())->toBeTrue();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-RE-005: Dropped timestamp cleared on re-enrollment
```php
it('dropped timestamp cleared on re-enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'dropped_at' => now()->subDays(30),
        'drop_reason' => 'Testing',
    ]);

    // Re-enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    expect($result->enrollment->dropped_at)->toBeNull();
    expect($result->enrollment->drop_reason)->toBeNull();
    expect($result->enrollment->enrolled_at)->not->toBeNull();
});
```
**Priority**: Medium
**Existing**: ⚠️ Implied by code

---

### describe('Re-enrollment with Progress Preserved')

#### TC-RE-006: Progress preserved when requested
```php
it('progress preserved when requested', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Create dropped enrollment with 2 completed courses
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 67,
    ]);

    foreach ($courses as $i => $course) {
        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $droppedEnrollment->id,
            'course_id' => $course->id,
            'position' => $i + 1,
            'state' => $i < 2 ? CompletedCourseState::$name : AvailableCourseState::$name,
            'completed_at' => $i < 2 ? now()->subDays(30) : null,
        ]);
    }

    // Re-enroll WITH preserve
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->reactivateEnrollment($droppedEnrollment, preserveProgress: true);

    // Progress should be maintained
    $courseProgress = $result->enrollment->courseProgress()->orderBy('position')->get();

    expect($courseProgress[0]->isCompleted())->toBeTrue();
    expect($courseProgress[1]->isCompleted())->toBeTrue();
    expect($courseProgress[2]->isAvailable())->toBeTrue();
});
```
**Priority**: High
**Existing**: ⚠️ Unit test exists

#### TC-RE-007: Course enrollments re-linked when progress preserved
```php
it('course enrollments re-linked when progress preserved', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create dropped enrollment
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Old course enrollment was dropped
    $oldCourseEnrollment = Enrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $course->id,
        'course_enrollment_id' => $oldCourseEnrollment->id,
        'position' => 1,
        'state' => AvailableCourseState::$name,
    ]);

    // Re-enroll with preserve
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->reactivateEnrollment($droppedEnrollment, preserveProgress: true);

    // Course enrollment should be new/active
    $courseProgress = $result->enrollment->courseProgress()->first();
    expect($courseProgress->courseEnrollment->isActive())->toBeTrue();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-RE-008: Locked courses remain locked when progress preserved
```php
it('locked courses remain locked when progress preserved', function () {
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

    // Create dropped enrollment - only first completed
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $courses[0]->id,
        'position' => 1,
        'state' => CompletedCourseState::$name,
    ]);
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $courses[1]->id,
        'position' => 2,
        'state' => AvailableCourseState::$name,
    ]);
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $courses[2]->id,
        'position' => 3,
        'state' => LockedCourseState::$name,
    ]);

    // Re-enroll with preserve
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->reactivateEnrollment($droppedEnrollment, preserveProgress: true);

    $courseProgress = $result->enrollment->courseProgress()->orderBy('position')->get();

    // Third course should still be locked
    expect($courseProgress[2]->isLocked())->toBeTrue();
    expect($courseProgress[2]->course_enrollment_id)->toBeNull();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('HTTP Re-enrollment Flow')

#### TC-RE-009: HTTP endpoint handles re-enrollment
```php
it('HTTP endpoint handles re-enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create dropped enrollment
    LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $response->assertRedirect();

    // Should be enrolled again
    $enrollment = LearningPathEnrollment::where('user_id', $learner->id)
        ->where('learning_path_id', $path->id)
        ->first();

    expect($enrollment->isActive())->toBeTrue();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-RE-010: User can choose to preserve progress via UI
```php
it('user can choose to preserve progress via UI', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create dropped enrollment with progress
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 50,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $course->id,
        'position' => 1,
        'state' => CompletedCourseState::$name,
    ]);

    // Post with preserve flag
    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path), [
            'preserve_progress' => true,
        ]);

    $response->assertRedirect();

    // Progress should be preserved
    $droppedEnrollment->refresh();
    expect($droppedEnrollment->courseProgress()->first()->isCompleted())->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Re-enrollment Validation')

#### TC-RE-011: Cannot re-enroll if already active
```php
it('cannot re-enroll if already actively enrolled', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Active enrollment exists
    LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Try to enroll again
    $enrollmentService = app(PathEnrollmentServiceContract::class);

    expect(fn () => $enrollmentService->enroll($learner, $path))
        ->toThrow(AlreadyEnrolledInPathException::class);
});
```
**Priority**: Critical
**Existing**: ✅ Covered

#### TC-RE-012: Cannot re-enroll in unpublished path
```php
it('cannot re-enroll in unpublished path', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->unpublished()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Dropped enrollment exists
    LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Try to re-enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);

    expect(fn () => $enrollmentService->enroll($learner, $path))
        ->toThrow(PathNotPublishedException::class);
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Re-enrollment Events')

#### TC-RE-013: PathEnrollmentCreated event dispatched on re-enrollment
```php
it('PathEnrollmentCreated event dispatched on re-enrollment', function () {
    Event::fake([PathEnrollmentCreated::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Re-enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);

    Event::assertDispatched(PathEnrollmentCreated::class);
});
```
**Priority**: Medium
**Existing**: ⚠️ Implied by code

#### TC-RE-014: Metrics incremented on re-enrollment
```php
it('metrics incremented on re-enrollment', function () {
    $metrics = $this->mock(MetricsService::class);
    $metrics->shouldReceive('increment')
        ->with('learning_path.enrollments.reactivated')
        ->once();
    $metrics->shouldReceive('increment')
        ->with(\Mockery::any())
        ->zeroOrMoreTimes();
    $metrics->shouldReceive('timing')
        ->zeroOrMoreTimes();

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Re-enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);
});
```
**Priority**: Low
**Existing**: ❌ Not covered

---

### describe('Edge Cases')

#### TC-RE-015: Multiple dropped enrollments (only latest considered)
```php
it('handles multiple historical enrollments correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Note: In practice, there should only be one enrollment per user/path
    // But test handles if multiple somehow exist
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Re-enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Should use the existing enrollment
    expect($result->enrollment->id)->toBe($droppedEnrollment->id);

    // Should only have one enrollment record
    $count = LearningPathEnrollment::where('user_id', $learner->id)
        ->where('learning_path_id', $path->id)
        ->count();
    expect($count)->toBe(1);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-RE-016: Re-enrollment after path courses changed
```php
it('re-enrollment handles path courses changed since dropping', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $oldCourse = Course::factory()->published()->create();
    $newCourse = Course::factory()->published()->create();

    // Original path had only one course
    $path->courses()->attach($oldCourse->id, ['position' => 1, 'is_required' => true]);

    // Create dropped enrollment with old structure
    $droppedEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $droppedEnrollment->id,
        'course_id' => $oldCourse->id,
        'position' => 1,
        'state' => CompletedCourseState::$name,
    ]);

    // Admin adds new course to path
    $path->courses()->attach($newCourse->id, ['position' => 2, 'is_required' => true]);

    // Re-enroll (with reset)
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Should have both courses now
    $courseProgress = $result->enrollment->courseProgress()->orderBy('position')->get();

    expect($courseProgress)->toHaveCount(2);
    expect($courseProgress[0]->course_id)->toBe($oldCourse->id);
    expect($courseProgress[1]->course_id)->toBe($newCourse->id);
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

## Test Summary

| Category | Test Count | Existing | New |
|----------|------------|----------|-----|
| Re-enrollment Detection | 2 | 1 | 1 |
| Progress Reset | 3 | 1 | 2 |
| Progress Preserved | 3 | 1 | 2 |
| HTTP Flow | 2 | 0 | 2 |
| Validation | 2 | 1 | 1 |
| Events | 2 | 0 | 2 |
| Edge Cases | 2 | 0 | 2 |
| **Total** | **16** | **4** | **12** |

---

## State Diagram

```
┌─────────────┐
│   (none)    │
└──────┬──────┘
       │ enroll()
       ▼
┌─────────────┐    drop()    ┌─────────────┐
│   active    │ ────────────►│   dropped   │
└──────┬──────┘              └──────┬──────┘
       │                            │
       │ complete()                 │ enroll()
       ▼                            │ (re-enrollment)
┌─────────────┐                     │
│  completed  │                     │
└─────────────┘                     │
                                    │
       ┌────────────────────────────┘
       │
       ▼
┌─────────────┐
│   active    │ (reactivated)
└─────────────┘
```

---

## Implementation Notes

- Test both `preserveProgress: true` and `false` scenarios
- Verify old progress records are properly cleaned up or maintained
- Test event dispatching with `Event::fake()`
- Check course enrollment re-linking behavior
- Test file: `tests/Feature/Journey/LearningPath/ReEnrollmentJourneyTest.php`

---

## Dependencies

- Requires: `PathEnrollmentService`
- Exceptions: `AlreadyEnrolledInPathException`, `PathNotPublishedException`
- Events: `PathEnrollmentCreated`
- Related: `02-learner-enrollment.md` (initial enrollment)
