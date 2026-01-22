# 03 - Learner Progress & Completion Journey

## Overview

This test plan covers the learner's journey of progressing through a learning path and completing it. It focuses on course progress tracking, state transitions, progress calculation, and path completion logic.

**Related Endpoints**:
- `GET /learner/learning-paths/{path}/progress` - View progress
- `POST /learner/learning-paths/{path}/courses/{course}/start` - Start course
- `POST /api/learning-paths/{path}/courses/{course}/complete` - Mark complete (triggered by lesson completion)

**Key Services**:
- `PathProgressService` - Handles progress tracking and unlock logic
- `UpdatePathProgressOnCourseCompletion` - Listener for cross-domain sync

---

## User Stories

### As Budi (enrolled learner):
> "Saya ingin melihat progress saya di learning path dan tahu kursus mana yang sudah selesai, sedang dikerjakan, atau masih terkunci."

### As Rina (completing learner):
> "Setelah menyelesaikan semua kursus wajib, saya ingin learning path saya ditandai sebagai selesai dan mendapatkan notifikasi."

### As Dewi (mixed progress):
> "Saya ingin fokus pada kursus wajib dulu, dan kursus opsional bisa saya kerjakan nanti tanpa mempengaruhi penyelesaian path."

---

## Existing Test Coverage

| Test | File | Status |
|------|------|--------|
| `unlockNextCourses creates enrollment` | `PathProgressServiceTest.php` | ✅ Exists |
| `reuses existing course enrollment` | `PathProgressServiceTest.php` | ✅ Exists |
| `calculateProgressPercentage` | `PathProgressServiceTest.php` | ✅ Exists |
| `only counts required courses` | `PathProgressServiceTest.php` | ✅ Exists |
| `isPathCompleted` | `PathProgressServiceTest.php` | ✅ Exists |
| `reverts progress on course drop` | `PathProgressServiceTest.php` | ✅ Exists |

**Gap**: Unit tests exist but no E2E HTTP tests for progress viewing, state transitions via UI, or event dispatching verification.

---

## Test Cases

### describe('View Progress Page')

#### TC-PC-001: Learner can view progress page for enrolled path
```php
it('learner can view progress page for enrolled path', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
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

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('learner/learning-paths/Progress')
        ->has('enrollment')
        ->has('progress', fn ($progress) => $progress
            ->where('totalCourses', 3)
            ->where('completedCourses', 0)
            ->has('courses', 3)
        )
    );
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-PC-002: Progress page shows correct course states
```php
it('progress page shows correct course states', function () {
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

    // Unlock second course
    app(PathProgressServiceContract::class)->unlockNextCourses($pathEnrollment);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->has('progress.courses', 3)
        ->where('progress.courses.0.state', 'completed')
        ->where('progress.courses.1.state', 'available')
        ->where('progress.courses.2.state', 'locked')
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-003: Progress page returns 403 for non-enrolled learner
```php
it('progress page returns 403 for non-enrolled learner', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertForbidden();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-004: Progress page returns 404 for unpublished path
```php
it('progress page returns 404 for unpublished path', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->unpublished()->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertNotFound();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Progress Calculation')

#### TC-PC-005: Progress percentage based on required courses only
```php
it('progress percentage based on required courses only', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(5)->create();

    // 3 required, 2 optional
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => $i < 3,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete 1 required course
    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->first();
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->where('progress.requiredCourses', 3)
        ->where('progress.completedRequiredCourses', 1)
        ->where('progress.requiredPercentage', 33) // 1/3 ≈ 33%
    );
});
```
**Priority**: High
**Existing**: ⚠️ Unit test exists, no E2E test

#### TC-PC-006: 100% progress when all required courses completed
```php
it('shows 100% progress when all required courses completed', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(4)->create();

    // 2 required, 2 optional
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => $i < 2,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete both required courses (leave optional incomplete)
    $courseProgress = $pathEnrollment->courseProgress()
        ->orderBy('position')
        ->take(2)
        ->get();

    foreach ($courseProgress as $progress) {
        $progress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->where('progress.requiredPercentage', 100)
        ->where('progress.completedCourses', 2)
        ->where('progress.totalCourses', 4)
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-007: All courses required when none explicitly marked
```php
it('treats all courses as required when none explicitly marked', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    // No is_required flag set (defaults to true)
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete 1 course
    $pathEnrollment->courseProgress()->first()->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->where('progress.requiredCourses', 3)
        ->where('progress.requiredPercentage', 33) // 1/3 ≈ 33%
    );
});
```
**Priority**: Medium
**Existing**: ⚠️ Partial unit test

---

### describe('Course State Transitions')

#### TC-PC-008: Starting a course changes state to in_progress
```php
it('starting a course changes state to in_progress', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Start the course
    $progressService = app(PathProgressServiceContract::class);
    $progressService->startCourse($pathEnrollment, $course);

    $courseProgress = $pathEnrollment->courseProgress()->first();

    expect($courseProgress->isInProgress())->toBeTrue();
    expect($courseProgress->started_at)->not->toBeNull();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-009: Cannot start a locked course
```php
it('cannot start a locked course', function () {
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

    // Try to start the second (locked) course
    $progressService = app(PathProgressServiceContract::class);
    $progressService->startCourse($pathEnrollment, $courses[1]);

    $courseProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first();

    // Should remain locked
    expect($courseProgress->isLocked())->toBeTrue();
    expect($courseProgress->started_at)->toBeNull();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-010: Completing course triggers state update via event
```php
it('completing course triggers state update via event', function () {
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

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Get first course enrollment
    $courseProgress = $pathEnrollment->courseProgress()->orderBy('position')->first();
    $courseEnrollment = $courseProgress->courseEnrollment;

    // Simulate completing the course enrollment
    $courseEnrollment->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    // Trigger the listener
    $progressService = app(PathProgressServiceContract::class);
    $progressService->onCourseCompleted($pathEnrollment, $courseEnrollment);

    // Verify course progress updated
    $courseProgress->refresh();
    expect($courseProgress->isCompleted())->toBeTrue();

    // Verify events dispatched
    Event::assertDispatched(PathProgressUpdated::class);
    Event::assertDispatched(CourseUnlockedInPath::class);
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit test exists

---

### describe('Path Completion')

#### TC-PC-011: Path marked complete when all required courses done
```php
it('path marked complete when all required courses done', function () {
    Event::fake([PathCompleted::class]);

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

    $progressService = app(PathProgressServiceContract::class);

    // Complete each course in sequence
    foreach ($courses as $course) {
        $courseProgress = $pathEnrollment->courseProgress()
            ->where('course_id', $course->id)
            ->first();

        $courseEnrollment = $courseProgress->courseEnrollment
            ?? Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

        $courseEnrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $courseProgress->update(['course_enrollment_id' => $courseEnrollment->id]);

        $progressService->onCourseCompleted($pathEnrollment->fresh(), $courseEnrollment);
    }

    $pathEnrollment->refresh();

    expect($pathEnrollment->isCompleted())->toBeTrue();
    expect($pathEnrollment->completed_at)->not->toBeNull();
    expect($pathEnrollment->progress_percentage)->toBe(100);

    Event::assertDispatched(PathCompleted::class);
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit test exists, no E2E

#### TC-PC-012: Path not complete when optional courses remain
```php
it('path complete even when optional courses remain incomplete', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    // First course required, others optional
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => $i === 0,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete only required course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = Enrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'course_id' => $courses[0]->id,
    ]);

    $courseProgress->update(['course_enrollment_id' => $courseEnrollment->id]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->onCourseCompleted($pathEnrollment, $courseEnrollment);

    $pathEnrollment->refresh();

    expect($pathEnrollment->isCompleted())->toBeTrue();
    expect($pathEnrollment->progress_percentage)->toBe(100);
});
```
**Priority**: High
**Existing**: ⚠️ Unit test exists

#### TC-PC-013: Completion timestamp recorded correctly
```php
it('completion timestamp recorded correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Travel forward in time
    $this->travel(5)->days();

    // Complete the course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->onCourseCompleted($pathEnrollment->fresh(), $courseEnrollment);

    $pathEnrollment->refresh();

    expect($pathEnrollment->completed_at)->not->toBeNull();
    expect($pathEnrollment->completed_at->diffInDays($pathEnrollment->enrolled_at))->toBe(5);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Progress Display Information')

#### TC-PC-014: Progress shows course titles and descriptions
```php
it('progress shows course titles and descriptions', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create([
        'title' => 'Pengantar Keamanan Siber',
        'description' => 'Pelajari dasar-dasar keamanan siber',
    ]);

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->has('progress.courses.0', fn ($item) => $item
            ->where('course.title', 'Pengantar Keamanan Siber')
            ->where('course.description', 'Pelajari dasar-dasar keamanan siber')
        )
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PC-015: Progress shows timestamps for each course
```php
it('progress shows timestamps for each course', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Start and complete the course
    $progressService = app(PathProgressServiceContract::class);
    $progressService->startCourse($pathEnrollment, $course);

    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->has('progress.courses.0', fn ($item) => $item
            ->has('started_at')
            ->has('completed_at')
            ->has('unlocked_at')
        )
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

#### TC-PC-016: Progress shows required vs optional indicator
```php
it('progress shows required vs optional indicator', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(2)->create();

    $path->courses()->attach($courses[0]->id, ['position' => 1, 'is_required' => true]);
    $path->courses()->attach($courses[1]->id, ['position' => 2, 'is_required' => false]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->where('progress.courses.0.is_required', true)
        ->where('progress.courses.1.is_required', false)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Continue Learning Flow')

#### TC-PC-017: Can navigate to available course from progress page
```php
it('can navigate to available course from progress page', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->has('progress.courses.0', fn ($item) => $item
            ->where('can_start', true)
            ->has('course_url')
        )
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-018: Locked course shows prerequisites not met
```php
it('locked course shows prerequisites not met', function () {
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
    $enrollmentService->enroll($learner, $path);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->has('progress.courses.1', fn ($item) => $item
            ->where('state', 'locked')
            ->where('can_start', false)
        )
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Progress Persistence')

#### TC-PC-019: Progress survives logout and login
```php
it('progress survives logout and login', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
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
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // "Logout" (new request without acting as)
    // "Login" again
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertInertia(fn ($page) => $page
        ->where('progress.completedCourses', 1)
        ->where('progress.courses.0.state', 'completed')
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-PC-020: Progress percentage updates in database
```php
it('progress percentage updates in database', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
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

    expect($pathEnrollment->progress_percentage)->toBe(0);

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->onCourseCompleted($pathEnrollment->fresh(), $courseEnrollment);

    $pathEnrollment->refresh();

    expect($pathEnrollment->progress_percentage)->toBe(25); // 1/4 = 25%
});
```
**Priority**: High
**Existing**: ⚠️ Unit test exists

---

### describe('Edge Cases - Progress Calculation')

#### TC-PC-021: Progress with zero courses
```php
it('handles path with zero courses gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    // No courses attached
    $enrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $progressService = app(PathProgressServiceContract::class);
    $progress = $progressService->getProgress($enrollment);

    expect($progress->totalCourses)->toBe(0);
    expect($progress->overallPercentage->value())->toBe(0);
    expect($progress->requiredPercentage)->toBe(0);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-PC-022: Progress rounding edge cases
```php
it('progress percentage rounds correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Create progress - 1 of 3 complete (33.33%)
    foreach ($courses as $i => $course) {
        LearningPathCourseProgress::create([
            'learning_path_enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'position' => $i + 1,
            'state' => $i === 0 ? CompletedCourseState::$name : LockedCourseState::$name,
        ]);
    }

    $progressService = app(PathProgressServiceContract::class);
    $percentage = $progressService->calculateProgressPercentage($enrollment);

    expect($percentage)->toBe(33); // Rounds down from 33.33
});
```
**Priority**: Low
**Existing**: ⚠️ Implicit in unit tests

---

### describe('Event Dispatching')

#### TC-PC-023: PathProgressUpdated event contains correct data
```php
it('PathProgressUpdated event contains correct data', function () {
    Event::fake([PathProgressUpdated::class]);

    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
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

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->update(['status' => 'completed', 'completed_at' => now()]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->onCourseCompleted($pathEnrollment->fresh(), $courseEnrollment);

    Event::assertDispatched(PathProgressUpdated::class, function ($event) use ($courses) {
        return $event->previousPercentage === 0
            && $event->newPercentage === 25
            && $event->courseId === $courses[0]->id;
    });
});
```
**Priority**: Medium
**Existing**: ⚠️ Partially covered

#### TC-PC-024: CourseUnlockedInPath event dispatched on unlock
```php
it('CourseUnlockedInPath event dispatched when course unlocks', function () {
    Event::fake([CourseUnlockedInPath::class]);

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
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    $progressService = app(PathProgressServiceContract::class);
    $progressService->unlockNextCourses($pathEnrollment);

    Event::assertDispatched(CourseUnlockedInPath::class, function ($event) use ($courses) {
        return $event->course->id === $courses[1]->id
            && $event->position === 2;
    });
});
```
**Priority**: Medium
**Existing**: ✅ Exists in unit test

---

### describe('Concurrent Progress')

#### TC-PC-025: Multiple paths progress tracked independently
```php
it('multiple paths progress tracked independently', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    // Create two paths with overlapping course
    $sharedCourse = Course::factory()->published()->create();
    $path1Course = Course::factory()->published()->create();
    $path2Course = Course::factory()->published()->create();

    $path1 = LearningPath::factory()->published()->create(['title' => 'Path 1']);
    $path2 = LearningPath::factory()->published()->create(['title' => 'Path 2']);

    $path1->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
    $path1->courses()->attach($path1Course->id, ['position' => 2, 'is_required' => true]);

    $path2->courses()->attach($sharedCourse->id, ['position' => 1, 'is_required' => true]);
    $path2->courses()->attach($path2Course->id, ['position' => 2, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result1 = $enrollmentService->enroll($learner, $path1);
    $result2 = $enrollmentService->enroll($learner, $path2);

    // Complete shared course in path 1
    $courseProgress = $result1->enrollment->courseProgress()
        ->where('course_id', $sharedCourse->id)
        ->first();

    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Path 2's progress should be independent
    $path2Progress = $result2->enrollment->courseProgress()
        ->where('course_id', $sharedCourse->id)
        ->first();

    expect($path2Progress->isAvailable())->toBeTrue(); // Not completed in path 2
    expect($result2->enrollment->progress_percentage)->toBe(0);
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Progress with Dropped Courses')

#### TC-PC-026: Dropping course reverts progress
```php
it('dropping course reverts progress correctly', function () {
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

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;

    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);
    $pathEnrollment->update(['progress_percentage' => 50]);

    // Drop the course enrollment
    $courseEnrollment->update(['status' => 'dropped']);

    // Trigger listener
    $listener = app(UpdatePathProgressOnCourseDrop::class);
    $listener->handle(new UserDropped($courseEnrollment, 'Testing'));

    $courseProgress->refresh();
    $pathEnrollment->refresh();

    expect($courseProgress->isAvailable())->toBeTrue();
    expect($pathEnrollment->progress_percentage)->toBe(0);
});
```
**Priority**: High
**Existing**: ✅ Exists

#### TC-PC-027: Dropping course in completed path reverts to active
```php
it('dropping course in completed path reverts to active', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create completed enrollment
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

    $listener = app(UpdatePathProgressOnCourseDrop::class);
    $listener->handle(new UserDropped($courseEnrollment, 'Testing'));

    $pathEnrollment->refresh();

    expect($pathEnrollment->isActive())->toBeTrue();
    expect($pathEnrollment->completed_at)->toBeNull();
});
```
**Priority**: High
**Existing**: ✅ Exists

---

## Test Summary

| Category | Test Count | Existing | New |
|----------|------------|----------|-----|
| View Progress Page | 4 | 0 | 4 |
| Progress Calculation | 3 | 2 (unit) | 3 |
| Course State Transitions | 3 | 1 (unit) | 3 |
| Path Completion | 3 | 1 (unit) | 3 |
| Progress Display | 3 | 0 | 3 |
| Continue Learning Flow | 2 | 0 | 2 |
| Progress Persistence | 2 | 1 (unit) | 2 |
| Edge Cases | 2 | 0 | 2 |
| Event Dispatching | 2 | 1 | 1 |
| Concurrent Progress | 1 | 0 | 1 |
| Dropped Courses | 2 | 2 | 0 |
| **Total** | **27** | **8** | **24** |

---

## Edge Cases to Consider

1. **Race condition**: Two course completions at same time
2. **Course removed from path**: After learner started (handled in 07-edge-cases.md)
3. **Path unpublished**: While learner in progress (handled in 07-edge-cases.md)
4. **Database timeout**: During progress calculation
5. **Zero required courses**: All optional scenario
6. **Course enrollment completed outside path**: Direct completion sync

---

## Implementation Notes

- Use `PathProgressServiceContract` for all progress operations
- Test events with `Event::fake()` selectively
- Use factory states: `->active()`, `->completed()`, `->dropped()`
- Verify database state changes with `$model->refresh()`
- Test file: `tests/Feature/Journey/LearningPath/LearnerProgressCompletionTest.php`
- Import events: `PathProgressUpdated`, `CourseUnlockedInPath`, `PathCompleted`

---

## Dependencies

- Requires: `02-learner-enrollment.md` (enrollment must exist before progress)
- Related: `04-prerequisite-modes.md` (unlock logic varies by mode)
- Related: `05-cross-domain-sync.md` (course completion triggers)
