# 02 - Learner Enrollment Journey

## Overview

This test plan covers the learner's journey to enroll in a learning path. This is the critical conversion point from browsing to learning.

**Endpoints**:
- `GET /learner/learning-paths/{learningPath}` - View path details
- `POST /learner/learning-paths/{learningPath}/enroll` - Enroll in path

**Controller**: `LearningPathEnrollmentController`

---

## User Stories

### As Rina (new learner):
> "Saya ingin melihat detail learning path dan mendaftar jika sesuai dengan kebutuhan saya."

### As Budi (cautious learner):
> "Saya ingin melihat daftar kursus dalam learning path sebelum mendaftar."

---

## Existing Test Coverage

| Test | File | Status |
|------|------|--------|
| `enrolls user in learning path` | `LearningPathEnrollmentTest.php` | ✅ Exists |
| `prevents duplicate enrollment` | `LearningPathEnrollmentTest.php` | ✅ Exists |
| `prevents enrollment in unpublished path` | `LearningPathEnrollmentTest.php` | ✅ Exists |
| `shows learning path details with enrollment status` | `LearningPathEnrollmentTest.php` | ✅ Exists |
| `shows progress when enrolled` | `LearningPathEnrollmentTest.php` | ✅ Exists |

**Gap**: No E2E journey tests, no tests for edge cases (path with no courses, concurrent enrollment, etc.)

---

## Test Cases

### describe('View Learning Path Details')

#### TC-EN-001: View published learning path details as unenrolled learner
```php
it('can view published learning path details when not enrolled', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $creator = User::factory()->create(['role' => 'content_manager']);

    $path = LearningPath::factory()->published()->create([
        'title' => 'Jalur Keamanan Siber',
        'description' => 'Pelajari dasar-dasar keamanan siber',
        'created_by' => $creator->id,
    ]);

    $courses = Course::factory()->published()->count(3)->create();
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.show', $path));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('learner/learning-paths/Show')
        ->has('learningPath', fn ($lp) => $lp
            ->where('title', 'Jalur Keamanan Siber')
            ->has('courses', 3)
        )
        ->where('enrollment', null)
        ->where('progress', null)
        ->where('canEnroll', true)
    );
});
```
**Priority**: Critical
**Existing**: ✅ Similar exists

#### TC-EN-002: View learning path shows all courses in correct order
```php
it('shows all courses in correct order', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();

    $course1 = Course::factory()->published()->create(['title' => 'Kursus Pertama']);
    $course2 = Course::factory()->published()->create(['title' => 'Kursus Kedua']);
    $course3 = Course::factory()->published()->create(['title' => 'Kursus Ketiga']);

    $path->courses()->attach($course1->id, ['position' => 1, 'is_required' => true]);
    $path->courses()->attach($course2->id, ['position' => 2, 'is_required' => true]);
    $path->courses()->attach($course3->id, ['position' => 3, 'is_required' => false]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.show', $path));

    $response->assertInertia(fn ($page) => $page
        ->where('learningPath.courses.0.title', 'Kursus Pertama')
        ->where('learningPath.courses.0.position', 1)
        ->where('learningPath.courses.0.is_required', true)
        ->where('learningPath.courses.1.title', 'Kursus Kedua')
        ->where('learningPath.courses.1.position', 2)
        ->where('learningPath.courses.2.title', 'Kursus Ketiga')
        ->where('learningPath.courses.2.is_required', false)
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EN-003: Cannot view unpublished learning path as learner
```php
it('cannot view unpublished learning path as learner', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->unpublished()->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.show', $path));

    $response->assertForbidden();
});
```
**Priority**: Critical
**Existing**: ❌ Not covered (exists in CRUD test but not enrollment controller)

#### TC-EN-004: View learning path when already enrolled shows enrollment and progress
```php
it('shows enrollment and progress when already enrolled', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Create enrollment with course progress
    $enrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 33,
    ]);

    foreach ($courses as $i => $course) {
        $enrollment->courseProgress()->create([
            'course_id' => $course->id,
            'state' => $i === 0 ? 'completed' : ($i === 1 ? 'available' : 'locked'),
            'position' => $i + 1,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.show', $path));

    $response->assertInertia(fn ($page) => $page
        ->has('enrollment')
        ->where('enrollment.state', 'active')
        ->has('progress')
        ->where('progress.totalCourses', 3)
        ->where('progress.completedCourses', 1)
        ->where('canEnroll', false)
    );
});
```
**Priority**: High
**Existing**: ✅ Similar exists

---

### describe('Enrollment Action')

#### TC-EN-005: Successfully enroll in learning path
```php
it('successfully enrolls in learning path', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    Event::fake([PathEnrollmentCreated::class]);

    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $response->assertRedirect(route('learner.learning-paths.show', $path));
    $response->assertSessionHas('success');

    // Verify database records
    $this->assertDatabaseHas('learning_path_enrollments', [
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'state' => 'active',
        'progress_percentage' => 0,
    ]);

    // Verify course progress records created
    $enrollment = LearningPathEnrollment::where('user_id', $learner->id)->first();
    expect($enrollment->courseProgress)->toHaveCount(3);

    Event::assertDispatched(PathEnrollmentCreated::class);
});
```
**Priority**: Critical
**Existing**: ✅ Exists

#### TC-EN-006: Enrollment creates course enrollment for first course
```php
it('enrollment creates course enrollment for first course', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    // First course should have course enrollment linked
    $enrollment = LearningPathEnrollment::where('user_id', $learner->id)->first();
    $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

    expect($courseProgress[0]->course_enrollment_id)->not->toBeNull();
    expect($courseProgress[0]->isAvailable())->toBeTrue();

    // Verify the actual course enrollment
    $this->assertDatabaseHas('enrollments', [
        'user_id' => $learner->id,
        'course_id' => $courses[0]->id,
        'status' => 'active',
    ]);

    // Other courses should be locked without enrollment
    expect($courseProgress[1]->course_enrollment_id)->toBeNull();
    expect($courseProgress[1]->isLocked())->toBeTrue();
    expect($courseProgress[2]->course_enrollment_id)->toBeNull();
    expect($courseProgress[2]->isLocked())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ⚠️ Unit tested but not E2E

#### TC-EN-007: Enrollment reuses existing course enrollment
```php
it('enrollment reuses existing course enrollment if learner already enrolled in course', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(3)->create();

    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, [
            'position' => $i + 1,
            'is_required' => true,
        ]);
    }

    // Learner already enrolled in first course directly
    $existingEnrollment = Enrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'course_id' => $courses[0]->id,
    ]);

    $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    // Should reuse existing enrollment, not create duplicate
    $pathEnrollment = LearningPathEnrollment::where('user_id', $learner->id)->first();
    $courseProgress = $pathEnrollment->courseProgress()->first();

    expect($courseProgress->course_enrollment_id)->toBe($existingEnrollment->id);

    // Should only have one enrollment for this course
    $enrollmentCount = Enrollment::where('user_id', $learner->id)
        ->where('course_id', $courses[0]->id)
        ->count();
    expect($enrollmentCount)->toBe(1);
});
```
**Priority**: High
**Existing**: ⚠️ Unit tested but not E2E

#### TC-EN-008: Cannot enroll in unpublished learning path
```php
it('cannot enroll in unpublished learning path', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->unpublished()->create();

    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $response->assertRedirect(route('learner.learning-paths.browse'));
    $response->assertSessionHas('error');

    $this->assertDatabaseMissing('learning_path_enrollments', [
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);
});
```
**Priority**: Critical
**Existing**: ✅ Exists

#### TC-EN-009: Cannot enroll when already enrolled (duplicate prevention)
```php
it('cannot enroll when already enrolled', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();

    // Already enrolled
    LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $response->assertRedirect();
    $response->assertSessionHas('warning');

    // Should still have only one enrollment
    $count = LearningPathEnrollment::where('user_id', $learner->id)
        ->where('learning_path_id', $path->id)
        ->count();
    expect($count)->toBe(1);
});
```
**Priority**: Critical
**Existing**: ✅ Exists

#### TC-EN-010: Cannot enroll when path enrollment is completed
```php
it('cannot enroll when already completed the path', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();

    // Already completed
    LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $response->assertRedirect();
    $response->assertSessionHas('warning');
});
```
**Priority**: High
**Existing**: ❌ Not covered

---

### describe('Enrollment with Different Path Configurations')

#### TC-EN-011: Enroll in path with mixed required/optional courses
```php
it('enrolls in path with mixed required and optional courses', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $courses = Course::factory()->published()->count(4)->create();

    // First 2 required, last 2 optional
    $path->courses()->attach($courses[0]->id, ['position' => 1, 'is_required' => true]);
    $path->courses()->attach($courses[1]->id, ['position' => 2, 'is_required' => true]);
    $path->courses()->attach($courses[2]->id, ['position' => 3, 'is_required' => false]);
    $path->courses()->attach($courses[3]->id, ['position' => 4, 'is_required' => false]);

    $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $enrollment = LearningPathEnrollment::where('user_id', $learner->id)->first();

    expect($enrollment->courseProgress)->toHaveCount(4);
    expect($enrollment->progress_percentage)->toBe(0);
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EN-012: Enroll in path with no courses (empty path)
```php
it('can enroll in path with no courses', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    // No courses attached

    $response = $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    // Should still enroll (or should it fail? Business decision needed)
    // If enrollment allowed:
    $response->assertRedirect();

    $enrollment = LearningPathEnrollment::where('user_id', $learner->id)->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->courseProgress)->toHaveCount(0);
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EN-013: Enroll in path with single course
```php
it('can enroll in path with single course', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $course = Course::factory()->published()->create();
    $path->courses()->attach($course->id, ['position' => 1, 'is_required' => true]);

    $this->actingAs($learner)
        ->post(route('learner.learning-paths.enroll', $path));

    $enrollment = LearningPathEnrollment::where('user_id', $learner->id)->first();

    expect($enrollment->courseProgress)->toHaveCount(1);

    // Single course should be immediately available
    $progress = $enrollment->courseProgress()->first();
    expect($progress->isAvailable())->toBeTrue();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Drop Enrollment')

#### TC-EN-014: Successfully drop from learning path
```php
it('can drop from learning path', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $enrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    Event::fake([PathDropped::class]);

    $response = $this->actingAs($learner)
        ->delete(route('learner.learning-paths.drop', $path), [
            'reason' => 'Tidak sesuai ekspektasi',
        ]);

    $response->assertRedirect(route('learner.learning-paths.index'));
    $response->assertSessionHas('success');

    $enrollment->refresh();
    expect($enrollment->isDropped())->toBeTrue();
    expect($enrollment->drop_reason)->toBe('Tidak sesuai ekspektasi');
    expect($enrollment->dropped_at)->not->toBeNull();

    Event::assertDispatched(PathDropped::class);
});
```
**Priority**: Critical
**Existing**: ✅ Similar exists

#### TC-EN-015: Cannot drop when not enrolled
```php
it('cannot drop when not enrolled', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    $response = $this->actingAs($learner)
        ->delete(route('learner.learning-paths.drop', $path));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EN-016: Cannot drop completed enrollment
```php
it('cannot drop completed enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $enrollment = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
    ]);

    $response = $this->actingAs($learner)
        ->delete(route('learner.learning-paths.drop', $path));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    // Should still be completed
    $enrollment->refresh();
    expect($enrollment->isCompleted())->toBeTrue();
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EN-017: Cannot drop another user's enrollment
```php
it('cannot drop another users enrollment', function () {
    $learner1 = User::factory()->create(['role' => 'learner']);
    $learner2 = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $enrollment = LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner2->id,
        'learning_path_id' => $path->id,
    ]);

    $response = $this->actingAs($learner1)
        ->delete(route('learner.learning-paths.drop', $path));

    // Should get error since learner1 has no active enrollment
    $response->assertRedirect();
    $response->assertSessionHas('error');

    // Learner2's enrollment should be unchanged
    $enrollment->refresh();
    expect($enrollment->isActive())->toBeTrue();
});
```
**Priority**: Critical
**Existing**: ✅ Similar exists

---

### describe('My Learning Paths List')

#### TC-EN-018: View list of enrolled learning paths
```php
it('can view list of enrolled learning paths', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $enrollments = [];
    for ($i = 1; $i <= 3; $i++) {
        $path = LearningPath::factory()->published()->create([
            'title' => "Path $i",
        ]);
        $enrollments[] = LearningPathEnrollment::factory()->active()->create([
            'user_id' => $learner->id,
            'learning_path_id' => $path->id,
            'progress_percentage' => $i * 20,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('learner/learning-paths/Index')
        ->has('enrollments.data', 3)
    );
});
```
**Priority**: High
**Existing**: ✅ Exists

#### TC-EN-019: Filter enrolled paths by status
```php
it('can filter enrolled paths by status', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    // Create enrollments with different statuses
    LearningPathEnrollment::factory()->active()->count(2)->create([
        'user_id' => $learner->id,
    ]);
    LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
    ]);
    LearningPathEnrollment::factory()->dropped()->create([
        'user_id' => $learner->id,
    ]);

    // Filter by active
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.index', ['status' => 'active']));

    $response->assertInertia(fn ($page) => $page
        ->has('enrollments.data', 2)
    );

    // Filter by completed
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.index', ['status' => 'completed']));

    $response->assertInertia(fn ($page) => $page
        ->has('enrollments.data', 1)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EN-020: Only shows current user's enrollments
```php
it('only shows current users enrollments', function () {
    $learner1 = User::factory()->create(['role' => 'learner']);
    $learner2 = User::factory()->create(['role' => 'learner']);

    LearningPathEnrollment::factory()->active()->count(3)->create([
        'user_id' => $learner1->id,
    ]);
    LearningPathEnrollment::factory()->active()->count(2)->create([
        'user_id' => $learner2->id,
    ]);

    $response = $this->actingAs($learner1)
        ->get(route('learner.learning-paths.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('enrollments.data', 3)
    );
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

---

### describe('View Progress Page')

#### TC-EN-021: View detailed progress page
```php
it('can view detailed progress page', function () {
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
        'progress_percentage' => 33,
    ]);

    foreach ($courses as $i => $course) {
        $enrollment->courseProgress()->create([
            'course_id' => $course->id,
            'state' => $i === 0 ? 'completed' : ($i === 1 ? 'available' : 'locked'),
            'position' => $i + 1,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('learner/learning-paths/Progress')
        ->has('progress.courses', 3)
        ->where('progress.completedCourses', 1)
        ->where('progress.lockedCourses', 1)
        ->where('progress.availableCourses', 1)
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-EN-022: Cannot view progress when not enrolled
```php
it('cannot view progress when not enrolled', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $path = LearningPath::factory()->published()->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertRedirect(route('learner.learning-paths.show', $path));
    $response->assertSessionHas('error');
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-EN-023: Can view progress of completed enrollment
```php
it('can view progress of completed enrollment', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    $enrollment = LearningPathEnrollment::factory()->completed()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $path->id,
        'progress_percentage' => 100,
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.progress', $path));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('enrollment.state', 'completed')
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

#### TC-EN-024: Cannot view another user's progress
```php
it('cannot view another users progress', function () {
    $learner1 = User::factory()->create(['role' => 'learner']);
    $learner2 = User::factory()->create(['role' => 'learner']);

    $path = LearningPath::factory()->published()->create();
    LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner2->id,
        'learning_path_id' => $path->id,
    ]);

    $response = $this->actingAs($learner1)
        ->get(route('learner.learning-paths.progress', $path));

    // Learner1 has no enrollment, should get error
    $response->assertRedirect();
    $response->assertSessionHas('error');
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

---

## Test Summary

| Category | Test Count | Existing | New |
|----------|------------|----------|-----|
| View Learning Path Details | 4 | 2 | 2 |
| Enrollment Action | 6 | 3 | 3 |
| Path Configurations | 3 | 0 | 3 |
| Drop Enrollment | 4 | 1 | 3 |
| My Learning Paths List | 3 | 1 | 2 |
| View Progress Page | 4 | 0 | 4 |
| **Total** | **24** | **7** | **17** |

---

## Edge Cases to Consider

1. **Concurrent enrollment attempts**: Two requests at the same time
2. **Path published status change during enrollment**: Path unpublished between page load and submit
3. **Course removed from path between page load and enrollment**: Course no longer in path
4. **Enrollment timestamps**: Verify `enrolled_at` is set correctly
5. **Transaction rollback**: If course enrollment fails, path enrollment should rollback

---

## Implementation Notes

- Use `Event::fake()` selectively to verify events are dispatched
- Test both happy paths and error handling
- Verify flash messages are in Bahasa Indonesia
- Test file: `tests/Feature/Journey/LearningPath/LearnerEnrollmentJourneyTest.php`
