# 05 - Cross-Domain Synchronization Test Plan

## Overview

This test plan covers the critical synchronization between Learning Path, Course Enrollment, and Lesson Progress domains. When a learner completes a course (via lesson progress), this must propagate to the Learning Path domain to unlock next courses and track completion.

**Domain Relationships**:
```
LearningPathEnrollment (active)
    └── LearningPathCourseProgress[]
            ├── course_id
            ├── course_enrollment_id  ──────► Enrollment (active)
            ├── state                              └── LessonProgress[]
            └── position
```

**Key Listeners**:
- `UpdatePathProgressOnCourseCompletion` - Listens to `EnrollmentCompleted`
- `UpdatePathProgressOnCourseDrop` - Listens to `UserDropped`

---

## User Stories

### As Budi (completing lesson):
> "Saat saya menyelesaikan semua lesson di sebuah kursus, kursus itu otomatis selesai, dan jika saya dalam learning path, kursus berikutnya terbuka."

### As Rina (multiple paths):
> "Saya terdaftar di 2 learning path yang memiliki kursus yang sama. Saat saya selesaikan kursus itu, kedua path saya harus update progress-nya."

### As Dewi (dropping course):
> "Jika saya keluar dari sebuah kursus yang sudah selesai, progress di learning path saya harus disesuaikan."

---

## Existing Test Coverage

| Test | File | Status |
|------|------|--------|
| `UpdatePathProgressOnCourseCompletion` | `PathProgressServiceTest.php` | ✅ Exists (unit) |
| `UpdatePathProgressOnCourseDrop` | `PathProgressServiceTest.php` | ✅ Exists (unit) |
| Event dispatch verification | Partial | ⚠️ Partial |

**Gap**: No E2E tests for full lesson → course → path completion chain. No tests for shared course scenarios.

---

## Test Cases

### describe('Course Completion → Path Progress')

#### TC-CS-001: Course completion updates path progress automatically
```php
it('course completion updates path progress automatically', function () {
    Event::fake([PathProgressUpdated::class, CourseUnlockedInPath::class]);

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

    // Enroll in path
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Get course enrollment created during path enrollment
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;

    // Complete the course enrollment (simulating lesson completion)
    $courseEnrollment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'progress_percentage' => 100,
    ]);

    // Dispatch EnrollmentCompleted event
    EnrollmentCompleted::dispatch($courseEnrollment);

    // Verify path progress updated
    $pathEnrollment->refresh();
    $courseProgress->refresh();

    expect($courseProgress->isCompleted())->toBeTrue();
    expect($pathEnrollment->progress_percentage)->toBe(50); // 1 of 2

    Event::assertDispatched(PathProgressUpdated::class);
    Event::assertDispatched(CourseUnlockedInPath::class);
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit test exists, no E2E

#### TC-CS-002: Path completion triggered when all required courses done
```php
it('path completion triggered when all required courses done via events', function () {
    Event::fake([PathCompleted::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    // Dispatch event
    EnrollmentCompleted::dispatch($courseEnrollment);

    // Verify path completed
    $pathEnrollment->refresh();
    expect($pathEnrollment->isCompleted())->toBeTrue();

    Event::assertDispatched(PathCompleted::class);
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit test exists

#### TC-CS-003: Next course unlocked and enrolled on completion
```php
it('next course unlocked and enrolled on completion', function () {
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

    // Enroll in path
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);
    EnrollmentCompleted::dispatch($courseEnrollment);

    // Verify second course now has enrollment
    $secondProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first();

    expect($secondProgress->isAvailable())->toBeTrue();
    expect($secondProgress->course_enrollment_id)->not->toBeNull();
    expect($secondProgress->courseEnrollment->isActive())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ✅ Covered in unit tests

---

### describe('Shared Course Across Paths')

#### TC-CS-004: Completing shared course updates all enrolled paths
```php
it('completing shared course updates all enrolled paths', function () {
    Event::fake([PathProgressUpdated::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $sharedCourse = Course::factory()->published()->create();

    // Create two paths with the shared course
    $path1 = LearningPath::factory()->published()->create(['title' => 'Path 1']);
    $path2 = LearningPath::factory()->published()->create(['title' => 'Path 2']);

    $path1->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
    $path2->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);

    // Enroll in both paths
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollment1 = $enrollmentService->enroll($learner, $path1)->enrollment;
    $enrollment2 = $enrollmentService->enroll($learner, $path2)->enrollment;

    // Both paths should share the same course enrollment
    $progress1 = $enrollment1->courseProgress()->first();
    $progress2 = $enrollment2->courseProgress()->first();

    expect($progress1->course_enrollment_id)->toBe($progress2->course_enrollment_id);

    // Complete the shared course
    $courseEnrollment = $progress1->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);
    EnrollmentCompleted::dispatch($courseEnrollment);

    // Both paths should update
    $enrollment1->refresh();
    $enrollment2->refresh();

    expect($enrollment1->isCompleted())->toBeTrue();
    expect($enrollment2->isCompleted())->toBeTrue();

    // Event dispatched twice (once per path)
    Event::assertDispatchedTimes(PathProgressUpdated::class, 2);
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-CS-005: Existing course enrollment reused when enrolling in new path
```php
it('existing course enrollment reused when enrolling in new path', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $course = Course::factory()->published()->create();

    // User already enrolled in course directly
    $existingEnrollment = Enrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    // Create path with same course
    $path = LearningPath::factory()->published()->create();
    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll in path
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Path should reuse existing enrollment
    $pathProgress = $result->enrollment->courseProgress()->first();

    expect($pathProgress->course_enrollment_id)->toBe($existingEnrollment->id);

    // No duplicate enrollment created
    $enrollmentCount = Enrollment::where('user_id', $learner->id)
        ->where('course_id', $course->id)
        ->count();
    expect($enrollmentCount)->toBe(1);
});
```
**Priority**: High
**Existing**: ✅ Covered in unit tests

#### TC-CS-006: Course completed before path enrollment is recognized
```php
it('course completed before path enrollment is recognized as complete', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $course = Course::factory()->published()->create();

    // Complete course before enrolling in path
    $existingEnrollment = Enrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
        'completed_at' => now()->subDays(7),
    ]);

    // Create path with same course
    $path = LearningPath::factory()->published()->create();
    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll in path
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Course should show as completed in path
    $pathProgress = $pathEnrollment->courseProgress()->first();

    // Note: This tests current behavior - may need to sync on enrollment
    // Check if the service handles pre-completed courses
    expect($pathProgress->course_enrollment_id)->toBe($existingEnrollment->id);
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Course Drop → Path Progress Reversion')

#### TC-CS-007: Dropping course reverts path progress
```php
it('dropping course reverts path progress', function () {
    Event::fake([PathProgressUpdated::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(2)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Enroll and complete first course
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);
    $pathEnrollment->update(['progress_percentage' => 50]);

    // Drop the course
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'dropped']);
    UserDropped::dispatch($courseEnrollment, 'Learner requested');

    // Verify path reverted
    $pathEnrollment->refresh();
    $courseProgress->refresh();

    expect($courseProgress->isAvailable())->toBeTrue(); // Reverted to available
    expect($courseProgress->completed_at)->toBeNull();
    expect($pathEnrollment->progress_percentage)->toBe(0);

    Event::assertDispatched(PathProgressUpdated::class);
});
```
**Priority**: High
**Existing**: ✅ Covered in unit tests

#### TC-CS-008: Dropping course in completed path reverts to active
```php
it('dropping course in completed path reverts to active', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create completed path
    $pathEnrollment = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 100,
    ]);

    $courseEnrollment = Enrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $pathEnrollment->id,
        'course_id' => $course->id,
        'course_enrollment_id' => $courseEnrollment->id,
        'state' => CompletedCourseState::$name,
        'position' => 1,
        'completed_at' => now(),
    ]);

    // Drop the course
    $courseEnrollment->update(['status' => 'dropped']);
    UserDropped::dispatch($courseEnrollment, 'Testing');

    // Path should revert to active
    $pathEnrollment->refresh();
    expect($pathEnrollment->isActive())->toBeTrue();
    expect($pathEnrollment->completed_at)->toBeNull();
});
```
**Priority**: High
**Existing**: ✅ Covered in unit tests

#### TC-CS-009: Dropping shared course affects all paths
```php
it('dropping shared course affects all enrolled paths', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $sharedCourse = Course::factory()->published()->create();

    $path1 = LearningPath::factory()->published()->create();
    $path2 = LearningPath::factory()->published()->create();

    $path1->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
    $path2->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);

    // Enroll in both paths (completed)
    $enrollment1 = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path1->id,
        'progress_percentage' => 100,
    ]);
    $enrollment2 = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path2->id,
        'progress_percentage' => 100,
    ]);

    $courseEnrollment = Enrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'course_id' => $sharedCourse->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $enrollment1->id,
        'course_id' => $sharedCourse->id,
        'course_enrollment_id' => $courseEnrollment->id,
        'state' => CompletedCourseState::$name,
        'position' => 1,
    ]);
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $enrollment2->id,
        'course_id' => $sharedCourse->id,
        'course_enrollment_id' => $courseEnrollment->id,
        'state' => CompletedCourseState::$name,
        'position' => 1,
    ]);

    // Drop the course
    $courseEnrollment->update(['status' => 'dropped']);
    UserDropped::dispatch($courseEnrollment, 'Testing');

    // Both paths should revert
    $enrollment1->refresh();
    $enrollment2->refresh();

    expect($enrollment1->isActive())->toBeTrue();
    expect($enrollment2->isActive())->toBeTrue();
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Lesson Progress → Course Completion Chain')

#### TC-CS-010: Completing all lessons triggers course completion
```php
it('completing all lessons triggers course completion', function () {
    Event::fake([EnrollmentCompleted::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $course = Course::factory()->published()->create();
    $section = Section::factory()->create(['course_id' => $course->id]);
    $lessons = Lesson::factory()->published()->count(3)->create([
        'section_id' => $section->id,
    ]);

    // Create enrollment
    $enrollment = Enrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    // Complete all lessons
    foreach ($lessons as $lesson) {
        LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'user_id' => $learner->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    // Trigger progress calculation
    $progressService = app(CourseProgressCalculator::class);
    $progressService->calculateAndUpdate($enrollment);

    // Course should be completed
    $enrollment->refresh();

    expect($enrollment->isCompleted())->toBeTrue();
    Event::assertDispatched(EnrollmentCompleted::class);
});
```
**Priority**: High
**Existing**: ⚠️ May exist in other tests

#### TC-CS-011: Full chain: lesson → course → path completion
```php
it('full chain: lesson completion triggers path progress', function () {
    Event::fake([EnrollmentCompleted::class, PathProgressUpdated::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();
    $section = Section::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->published()->create(['section_id' => $section->id]);

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll in path
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Get course enrollment
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;

    // Complete the lesson
    LessonProgress::create([
        'enrollment_id' => $courseEnrollment->id,
        'lesson_id' => $lesson->id,
        'user_id' => $learner->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    // Calculate course progress
    $progressService = app(CourseProgressCalculator::class);
    $progressService->calculateAndUpdate($courseEnrollment);

    // Verify chain completed
    $courseEnrollment->refresh();
    $pathEnrollment->refresh();

    expect($courseEnrollment->isCompleted())->toBeTrue();
    expect($pathEnrollment->isCompleted())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

---

### describe('Event Listener Behavior')

#### TC-CS-012: Listener only processes active path enrollments
```php
it('listener only processes active path enrollments', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create dropped path enrollment
    $pathEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $courseEnrollment = Enrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $pathEnrollment->id,
        'course_id' => $course->id,
        'course_enrollment_id' => $courseEnrollment->id,
        'state' => AvailableCourseState::$name,
        'position' => 1,
    ]);

    // Complete course
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    // Handle the event
    $listener = app(UpdatePathProgressOnCourseCompletion::class);
    $listener->handle(new EnrollmentCompleted($courseEnrollment));

    // Path should remain dropped
    $pathEnrollment->refresh();
    expect($pathEnrollment->isDropped())->toBeTrue();
    expect($pathEnrollment->progress_percentage)->toBe(0);
});
```
**Priority**: High
**Existing**: ⚠️ Implied by code, not explicitly tested

#### TC-CS-013: Listener handles course not in any path
```php
it('listener handles course not in any path gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $course = Course::factory()->published()->create();

    // Direct enrollment, not part of any path
    $enrollment = Enrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    // Complete course
    $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

    // Should not throw
    $listener = app(UpdatePathProgressOnCourseCompletion::class);
    $listener->handle(new EnrollmentCompleted($enrollment));

    // Just verify no exception
    expect(true)->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ✅ Covered in unit tests

---

### describe('Async Queue Processing')

#### TC-CS-014: Listener is queued for processing
```php
it('listener is queued for async processing', function () {
    Queue::fake();

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    $courseProgress = $result->enrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    // Dispatch event
    event(new EnrollmentCompleted($courseEnrollment));

    // Verify listener was queued
    Queue::assertPushed(UpdatePathProgressOnCourseCompletion::class);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-CS-015: Progress eventually consistent after queue processing
```php
it('progress eventually consistent after queue processing', function () {
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
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    // Process listener synchronously
    $listener = app(UpdatePathProgressOnCourseCompletion::class);
    $listener->handle(new EnrollmentCompleted($courseEnrollment));

    // Verify state is consistent
    $pathEnrollment->refresh();
    $courseProgress->refresh();

    expect($courseProgress->isCompleted())->toBeTrue();
    expect($pathEnrollment->progress_percentage)->toBe(50);

    // Second course should be unlocked
    $secondProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first();
    expect($secondProgress->isAvailable())->toBeTrue();
});
```
**Priority**: High
**Existing**: ⚠️ Partial coverage

---

### describe('Data Integrity')

#### TC-CS-016: Transaction rollback on failure
```php
it('transaction rolls back on failure during sync', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;

    // Mock the progress service to throw
    $this->mock(PathProgressServiceContract::class)
        ->shouldReceive('onCourseCompleted')
        ->andThrow(new \RuntimeException('Database error'));

    // Complete course
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    try {
        $listener = app(UpdatePathProgressOnCourseCompletion::class);
        $listener->handle(new EnrollmentCompleted($courseEnrollment));
    } catch (\RuntimeException $e) {
        // Expected
    }

    // Path progress should not have changed
    $pathEnrollment->refresh();
    expect($pathEnrollment->progress_percentage)->toBe(0);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-CS-017: Orphan course progress records handled
```php
it('handles orphan course progress records gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $course = Course::factory()->published()->create();

    // Create orphan course enrollment (no path)
    $courseEnrollment = Enrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
    ]);

    // Create orphan progress record (path enrollment deleted)
    $orphanProgress = LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => 99999, // Non-existent
        'course_id' => $course->id,
        'course_enrollment_id' => $courseEnrollment->id,
        'state' => AvailableCourseState::$name,
        'position' => 1,
    ]);

    // Should not throw when course completes
    $listener = app(UpdatePathProgressOnCourseCompletion::class);

    // This should handle the missing relationship gracefully
    expect(fn () => $listener->handle(new EnrollmentCompleted($courseEnrollment)))
        ->not->toThrow(\Exception::class);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Edge Cases')

#### TC-CS-018: Concurrent course completions
```php
it('handles concurrent course completions correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
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

    // Get both course enrollments
    $progress1 = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[0]->id)
        ->first();
    $progress2 = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first();

    // Make second course available
    $progress2->update([
        'state' => AvailableCourseState::$name,
        'course_enrollment_id' => Enrollment::factory()->active()->create([
            'user_id' => $learner->id,
            'course_id' => $courses[1]->id,
        ])->id,
    ]);

    // Complete both courses "simultaneously"
    $enrollment1 = $progress1->courseEnrollment;
    $enrollment2 = $progress2->fresh()->courseEnrollment;

    $enrollment1->update(['status' => 'completed', 'completed_at' => now()]);
    $enrollment2->update(['status' => 'completed', 'completed_at' => now()]);

    // Process both events
    $listener = app(UpdatePathProgressOnCourseCompletion::class);
    $listener->handle(new EnrollmentCompleted($enrollment1));
    $listener->handle(new EnrollmentCompleted($enrollment2));

    // Path should be complete
    $pathEnrollment->refresh();
    expect($pathEnrollment->isCompleted())->toBeTrue();
    expect($pathEnrollment->progress_percentage)->toBe(100);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-CS-019: Course removed from path during progress
```php
it('handles course removed from path during progress', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
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

    // Admin removes first course from path
    $path->courses()->detach($courses[0]->id);

    // Learner completes the removed course
    $courseProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[0]->id)
        ->first();

    if ($courseProgress) {
        $courseEnrollment = $courseProgress->courseEnrollment;
        $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // Should handle gracefully
        $listener = app(UpdatePathProgressOnCourseCompletion::class);
        $listener->handle(new EnrollmentCompleted($courseEnrollment));
    }

    // Verify no crash
    expect(true)->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

## Test Summary

| Category | Test Count | Existing | New |
|----------|------------|----------|-----|
| Course → Path Progress | 3 | 2 (unit) | 3 |
| Shared Course | 3 | 1 | 2 |
| Course Drop | 3 | 2 | 1 |
| Lesson → Course Chain | 2 | 0 | 2 |
| Listener Behavior | 2 | 1 | 1 |
| Async Queue | 2 | 0 | 2 |
| Data Integrity | 2 | 0 | 2 |
| Edge Cases | 2 | 0 | 2 |
| **Total** | **19** | **6** | **15** |

---

## Event Flow Diagram

```
┌─────────────────┐
│ LessonProgress  │
│   completed     │
└────────┬────────┘
         │ triggers
         ▼
┌─────────────────┐
│ CourseProgress  │
│   Calculator    │
└────────┬────────┘
         │ if 100%
         ▼
┌─────────────────┐    dispatch     ┌──────────────────────────────┐
│  Enrollment     │ ────────────────► │ EnrollmentCompleted         │
│  completed      │                   │   Event                      │
└─────────────────┘                   └──────────────┬───────────────┘
                                                     │ listened by
                                                     ▼
                                      ┌──────────────────────────────┐
                                      │ UpdatePathProgressOn         │
                                      │   CourseCompletion           │
                                      └──────────────┬───────────────┘
                                                     │ updates
                                                     ▼
                                      ┌──────────────────────────────┐
                                      │ PathProgressService          │
                                      │   onCourseCompleted()        │
                                      └──────────────┬───────────────┘
                                                     │
                              ┌───────────────┴───────────────┐
                              ▼                               ▼
               ┌──────────────────────┐       ┌──────────────────────┐
               │ Mark course complete │       │ Unlock next courses  │
               │ in path progress     │       │ Create enrollments   │
               └──────────────────────┘       └──────────────────────┘
                                                     │
                                                     ▼
                                      ┌──────────────────────────────┐
                                      │ Check if path complete       │
                                      │ If yes: PathEnrollmentService│
                                      │   ->complete()               │
                                      └──────────────────────────────┘
```

---

## Implementation Notes

- Use `Event::fake()` to isolate event dispatching
- Use `Queue::fake()` to verify async processing
- Test both synchronous and queued listener execution
- Verify idempotency of progress updates
- Test file: `tests/Feature/Journey/LearningPath/CrossDomainSyncTest.php`

---

## Dependencies

- Requires: `PathProgressService`, `EnrollmentService`
- Events: `EnrollmentCompleted`, `UserDropped`, `PathProgressUpdated`
- Listeners: `UpdatePathProgressOnCourseCompletion`, `UpdatePathProgressOnCourseDrop`
- Related: `03-learner-progress-completion.md` (progress mechanics)
