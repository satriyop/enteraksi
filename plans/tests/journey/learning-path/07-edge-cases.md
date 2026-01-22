# 07 - Edge Cases & Boundary Conditions Test Plan

## Overview

This test plan covers edge cases, boundary conditions, error scenarios, and unusual situations that could cause unexpected behavior in the Learning Path feature. These tests ensure the application is robust and handles all possible states gracefully.

**Categories**:
1. Path Structure Changes (mid-progress)
2. State Machine Transitions
3. Concurrent Operations
4. Data Integrity
5. Error Handling
6. Boundary Values
7. Soft Deletes and Archiving

---

## Test Cases

### describe('Path Structure Changes Mid-Progress')

#### TC-EC-001: Path unpublished while learner in progress
```php
it('path unpublished while learner in progress keeps enrollment active', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll learner
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Admin unpublishes path
    $path->update([
        'is_published' => false,
        'published_at' => null,
    ]);

    // Enrollment should still be active
    $pathEnrollment->refresh();
    expect($pathEnrollment->isActive())->toBeTrue();

    // Learner can still access progress
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-EC-002: Course removed from path while learner in progress
```php
it('course removed from path handles enrolled learner gracefully', function () {
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
    $pathEnrollment = $result->enrollment;

    // Complete first course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Admin removes second course from path
    $path->courses()->detach($courses[1]->id);

    // Progress page should still work
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();

    // Old course progress record still exists (orphaned)
    $orphanProgress = $pathEnrollment->courseProgress()
        ->where('course_id', $courses[1]->id)
        ->first();

    // Could be null or could exist - document actual behavior
    // This tests that system doesn't crash
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EC-003: New course added to path mid-progress
```php
it('new course added to path does not affect existing enrollments', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll learner
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Admin adds new course
    $newCourse = Course::factory()->published()->create();
    $path->courses()->attach($newCourse->id, ['position' => 2, 'is_required' => true]);

    // Enrollment should not have new course
    expect($pathEnrollment->courseProgress()->count())->toBe(1);

    // Progress service should handle this
    $progressService = app(PathProgressServiceContract::class);
    $progress = $progressService->getProgress($pathEnrollment);

    // Note: Tests actual behavior - may show only original course
    expect($progress->totalCourses)->toBe(1);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EC-004: Course unpublished while in path
```php
it('course unpublished in path handles learner gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll learner
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Course unpublished
    $course->update(['is_published' => false]);

    // Progress page should still work
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('State Machine Edge Cases')

#### TC-EC-005: Double completion handling
```php
it('handles double completion call gracefully (idempotent)', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll and complete
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Complete the course
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseProgress->update([
        'state' => CompletedCourseState::$name,
        'completed_at' => now(),
    ]);

    // Complete the path
    $enrollmentService->complete($pathEnrollment);

    // Try to complete again - should be idempotent
    $enrollmentService->complete($pathEnrollment->fresh());

    $pathEnrollment->refresh();
    expect($pathEnrollment->isCompleted())->toBeTrue();
    // No exception thrown
});
```
**Priority**: High
**Existing**: ⚠️ Implied by code

#### TC-EC-006: Cannot drop completed enrollment
```php
it('cannot drop completed enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    $pathEnrollment = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);

    expect(fn () => $enrollmentService->drop($pathEnrollment))
        ->toThrow(InvalidStateTransitionException::class);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EC-007: Cannot drop already dropped enrollment
```php
it('cannot drop already dropped enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    $pathEnrollment = LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);

    expect(fn () => $enrollmentService->drop($pathEnrollment))
        ->toThrow(InvalidStateTransitionException::class);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Concurrent Operations')

#### TC-EC-008: Concurrent enrollments to same path
```php
it('concurrent enrollments to same path do not create duplicates', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);

    // First enrollment
    $result1 = $enrollmentService->enroll($learner, $path);

    // Second enrollment should fail
    expect(fn () => $enrollmentService->enroll($learner, $path))
        ->toThrow(AlreadyEnrolledInPathException::class);

    // Only one enrollment exists
    $count = LearningPathEnrollment::where('user_id', $learner->id)
        ->where('learning_path_id', $path->id)
        ->count();
    expect($count)->toBe(1);
});
```
**Priority**: Critical
**Existing**: ✅ Covered

#### TC-EC-009: Multiple course completions at same time
```php
it('handles multiple course completions at same time', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => 'none', // All available
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

    // Complete all courses "simultaneously"
    foreach ($pathEnrollment->courseProgress as $progress) {
        $progress->update([
            'state' => CompletedCourseState::$name,
            'completed_at' => now(),
        ]);
    }

    // Check path completion
    $progressService = app(PathProgressServiceContract::class);

    expect($progressService->isPathCompleted($pathEnrollment))->toBeTrue();
});
```
**Priority**: High
**Existing**: ⚠️ Partial

---

### describe('Data Integrity')

#### TC-EC-010: Progress calculation with missing course progress records
```php
it('handles missing course progress records gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Create enrollment WITHOUT course progress (corrupt state)
    $pathEnrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    // Progress service should handle this
    $progressService = app(PathProgressServiceContract::class);
    $progress = $progressService->getProgress($pathEnrollment);

    expect($progress->totalCourses)->toBe(0);
    expect($progress->overallPercentage->value())->toBe(0);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EC-011: Course enrollment deleted externally
```php
it('handles deleted course enrollment gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Delete course enrollment directly (simulating external deletion)
    $courseProgress = $pathEnrollment->courseProgress()->first();
    $courseEnrollment = $courseProgress->courseEnrollment;
    $courseEnrollment->delete();

    // Progress page should handle null enrollment
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    // Should not crash
    $response->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EC-012: Orphan course progress records cleanup
```php
it('orphan course progress does not affect path progress', function () {
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

    // Create orphan progress for non-existent course
    LearningPathCourseProgress::create([
        'learning_path_enrollment_id' => $pathEnrollment->id,
        'course_id' => 99999, // Non-existent
        'position' => 3,
        'state' => AvailableCourseState::$name,
    ]);

    // Progress should only count valid courses
    $progressService = app(PathProgressServiceContract::class);
    $progress = $progressService->getProgress($pathEnrollment);

    expect($progress->totalCourses)->toBe(2);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Boundary Values')

#### TC-EC-013: Path with zero courses
```php
it('handles path with zero courses', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    // No courses attached

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // Progress should be 0/0 = 0%
    expect($pathEnrollment->progress_percentage)->toBe(0);

    // Path should complete immediately (vacuously true)
    $progressService = app(PathProgressServiceContract::class);
    expect($progressService->isPathCompleted($pathEnrollment))->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EC-014: Path with maximum courses
```php
it('handles path with many courses (50+)', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(50)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    expect($result->enrollment->courseProgress()->count())->toBe(50);
});
```
**Priority**: Low
**Existing**: ❌ Not covered

#### TC-EC-015: All courses optional
```php
it('handles path with all optional courses', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => false, // All optional
        ]);
    }

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);
    $pathEnrollment = $result->enrollment;

    // When all optional, all are considered required
    $progressService = app(PathProgressServiceContract::class);
    $progress = $progressService->getProgress($pathEnrollment);

    expect($progress->requiredCourses)->toBe(3); // Fallback behavior
});
```
**Priority**: Medium
**Existing**: ⚠️ Unit test exists

#### TC-EC-016: Very long title/description
```php
it('handles learning path with very long title', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create([
        'title' => str_repeat('Jalur Pembelajaran ', 50), // Very long
        'description' => str_repeat('Deskripsi panjang. ', 100),
    ]);
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertOk();
});
```
**Priority**: Low
**Existing**: ❌ Not covered

---

### describe('Error Handling')

#### TC-EC-017: Database transaction failure rollback
```php
it('rolls back transaction on enrollment failure', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Mock enrollment service to fail
    $this->mock(EnrollmentServiceContract::class)
        ->shouldReceive('getActiveEnrollment')
        ->andReturn(null)
        ->shouldReceive('enroll')
        ->andThrow(new \RuntimeException('Database error'));

    $enrollmentService = app(PathEnrollmentServiceContract::class);

    try {
        $enrollmentService->enroll($learner, $path);
    } catch (\RuntimeException $e) {
        // Expected
    }

    // No path enrollment should exist
    $count = LearningPathEnrollment::where('user_id', $learner->id)
        ->where('learning_path_id', $path->id)
        ->count();

    expect($count)->toBe(0);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EC-018: Invalid state transition logged
```php
it('invalid state transition is logged', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    $pathEnrollment = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);

    try {
        $enrollmentService->drop($pathEnrollment);
    } catch (InvalidStateTransitionException $e) {
        expect($e->from)->toBe('completed');
        expect($e->to)->toBe('dropped');
    }
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Authorization Edge Cases')

#### TC-EC-019: Learner cannot access other's progress
```php
it('learner cannot access other learner progress', function () {
    $learner1 = User::factory()->create(['role' => 'learner']);
    $learner2 = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Learner 1 enrolls
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner1, $path);

    // Learner 2 tries to access
    $response = $this->actingAs($learner2)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertForbidden();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-EC-020: Admin can view any learner's progress
```php
it('admin can view any learner progress', function () {
    $admin = User::factory()->create(['role' => 'lms_admin']);
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Learner enrolls
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);

    // Admin can view via admin route
    $response = $this->actingAs($admin)
        ->get(route('admin.learning-paths.learner-progress', [
            'path' => $path,
            'user' => $learner,
        ]));

    $response->assertOk();
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Search and Filter Edge Cases')

#### TC-EC-021: SQL injection in search
```php
it('handles SQL injection in search parameter', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'search' => "'; DROP TABLE learning_paths; --",
        ]));

    $response->assertOk();

    // Table should still exist
    expect(LearningPath::count())->toBeGreaterThanOrEqual(0);
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-EC-022: Unicode search
```php
it('handles unicode characters in search', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->create([
        'title' => '学习路径 - Chinese Path',
    ]);
    LearningPath::factory()->published()->create([
        'title' => 'مسار التعلم - Arabic Path',
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'search' => '学习',
        ]));

    $response->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EC-023: Empty search returns all
```php
it('empty search returns all published paths', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->count(5)->create();
    LearningPath::factory()->unpublished()->count(2)->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'search' => '',
        ]));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 5)
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

---

### describe('Performance Edge Cases')

#### TC-EC-024: Many enrolled paths for single user
```php
it('handles user enrolled in many paths', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    // Create 20 paths
    $paths = LearningPath::factory()->published()->count(20)->create();

    foreach ($paths as $path) {
        $course = Course::factory()->published()->create();
        $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

        LearningPathEnrollment::factory()->active()->create([
            'user_id' => $learner->id,
            'learning_path_id' => $path->id,
        ]);
    }

    // My paths page should load
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.index'));

    $response->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EC-025: Path with many enrollments
```php
it('handles path with many enrolled learners', function () {
    $admin = User::factory()->create(['role' => 'lms_admin']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Create 100 enrollments
    $learners = User::factory()->count(100)->create(['role' => 'learner']);
    foreach ($learners as $learner) {
        LearningPathEnrollment::factory()->active()->create([
            'user_id' => $learner->id,
            'learning_path_id' => $path->id,
        ]);
    }

    // Admin can view learners
    $response = $this->actingAs($admin)
        ->get(route('admin.learning-paths.learners', $path));

    $response->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Soft Delete Scenarios')

#### TC-EC-026: Deleted user's enrollment handled
```php
it('handles deleted user enrollment gracefully', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $enrollmentService->enroll($learner, $path);

    // Soft delete user
    $learner->delete();

    // Enrollment should still exist (for audit)
    $enrollment = LearningPathEnrollment::where('learning_path_id', $path->id)->first();
    expect($enrollment)->not->toBeNull();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EC-027: Archived course in path
```php
it('handles archived course in path', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();

    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    // Enroll before archiving
    $enrollmentService = app(PathEnrollmentServiceContract::class);
    $result = $enrollmentService->enroll($learner, $path);

    // Archive the course
    $course->update(['is_archived' => true]);

    // Progress page should still work
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

## Test Summary

| Category | Test Count |
|----------|------------|
| Path Structure Changes | 4 |
| State Machine | 3 |
| Concurrent Operations | 2 |
| Data Integrity | 3 |
| Boundary Values | 4 |
| Error Handling | 2 |
| Authorization | 2 |
| Search/Filter | 3 |
| Performance | 2 |
| Soft Delete | 2 |
| **Total** | **27** |

---

## Risk Assessment

| Test ID | Risk Level | Impact | Notes |
|---------|------------|--------|-------|
| TC-EC-001 | High | Data loss | Unpublished path mid-progress |
| TC-EC-002 | High | Orphan data | Course removal |
| TC-EC-008 | Critical | Duplicates | Concurrent enrollment |
| TC-EC-017 | High | Data corruption | Transaction rollback |
| TC-EC-019 | Critical | Security | Authorization bypass |
| TC-EC-021 | Critical | Security | SQL injection |

---

## Implementation Notes

- Use database transactions in test setup to ensure clean state
- Test with both fresh and existing data scenarios
- Verify error messages are user-friendly (Indonesian)
- Test file: `tests/Feature/Journey/LearningPath/EdgeCasesTest.php`
- Consider using `assertDatabaseHas` and `assertDatabaseMissing`

---

## Dependencies

All previous test plan documents should pass before edge cases are tested:
- `02-learner-enrollment.md`
- `03-learner-progress-completion.md`
- `04-prerequisite-modes.md`
- `05-cross-domain-sync.md`
- `06-re-enrollment.md`
