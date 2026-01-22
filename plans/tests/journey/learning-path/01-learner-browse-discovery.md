# 01 - Learner Browse & Discovery Journey

## Overview

This test plan covers the learner's journey to discover and browse available learning paths. These are the first touchpoints before enrollment.

**Endpoint**: `GET /learner/learning-paths/browse`
**Controller**: `LearningPathEnrollmentController@browse`
**View**: `learner/learning-paths/Browse`

---

## User Stories

### As Rina (new employee):
> "Saya ingin melihat daftar learning path yang tersedia supaya saya bisa memilih pelatihan yang sesuai."

### As Budi (returning learner):
> "Saya ingin mencari learning path tentang keamanan siber untuk meningkatkan skill saya."

---

## Existing Test Coverage

| Test | File | Status |
|------|------|--------|
| `shows published learning paths` | `LearningPathEnrollmentTest.php` | ✅ Exists |
| `marks enrolled paths` | `LearningPathEnrollmentTest.php` | ✅ Exists |

**Gap**: No tests for search, filter, pagination, empty states.

---

## Test Cases

### describe('Browse Page Access')

#### TC-BD-001: Authenticated learner can access browse page
```php
it('authenticated learner can access browse page', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('learner/learning-paths/Browse')
    );
});
```
**Priority**: Critical
**Existing**: ⚠️ Partially covered

#### TC-BD-002: Unauthenticated user is redirected to login
```php
it('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('learner.learning-paths.browse'));

    $response->assertRedirect(route('login'));
});
```
**Priority**: Critical
**Existing**: ❌ Not covered

#### TC-BD-003: Admin and content manager can also access browse page
```php
it('admin and content manager can access browse page', function () {
    $admin = User::factory()->create(['role' => 'lms_admin']);
    $cm = User::factory()->create(['role' => 'content_manager']);

    $this->actingAs($admin)
        ->get(route('learner.learning-paths.browse'))
        ->assertOk();

    $this->actingAs($cm)
        ->get(route('learner.learning-paths.browse'))
        ->assertOk();
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Learning Path Listing')

#### TC-BD-004: Only published learning paths are displayed
```php
it('only published learning paths are displayed', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->count(3)->create();
    LearningPath::factory()->unpublished()->count(2)->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 3)
    );
});
```
**Priority**: Critical
**Existing**: ✅ Covered in `LearningPathEnrollmentTest`

#### TC-BD-005: Learning paths show correct metadata
```php
it('learning paths show correct metadata', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $creator = User::factory()->create(['role' => 'content_manager']);

    $path = LearningPath::factory()->published()->create([
        'title' => 'Jalur Keamanan Siber',
        'description' => 'Pelajari dasar-dasar keamanan siber',
        'created_by' => $creator->id,
        'difficulty_level' => 'beginner',
        'estimated_duration' => 120,
    ]);

    $courses = Course::factory()->published()->count(3)->create();
    foreach ($courses as $i => $course) {
        $path->courses()->attach($course->id, ['position' => $i + 1]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data.0', fn ($item) => $item
            ->where('title', 'Jalur Keamanan Siber')
            ->where('difficulty_level', 'beginner')
            ->where('estimated_duration', 120)
            ->where('courses_count', 3)
            ->has('creator', fn ($c) => $c
                ->where('name', $creator->name)
            )
        )
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-BD-006: Already enrolled paths are marked
```php
it('already enrolled paths are marked', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $enrolledPath = LearningPath::factory()->published()->create();
    $notEnrolledPath = LearningPath::factory()->published()->create();

    LearningPathEnrollment::factory()->active()->create([
        'user_id' => $learner->id,
        'learning_path_id' => $enrolledPath->id,
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertInertia(fn ($page) => $page
        ->has('enrolledPathIds', 1)
        ->where('enrolledPathIds.0', $enrolledPath->id)
    );
});
```
**Priority**: High
**Existing**: ✅ Covered in `LearningPathEnrollmentTest`

---

### describe('Search Functionality')

#### TC-BD-007: Search by title returns matching results
```php
it('search by title returns matching results', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->create([
        'title' => 'Jalur Keamanan Siber',
    ]);
    LearningPath::factory()->published()->create([
        'title' => 'Jalur Pengembangan Web',
    ]);
    LearningPath::factory()->published()->create([
        'title' => 'Jalur Data Science',
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', ['search' => 'Keamanan']));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 1)
        ->where('learningPaths.data.0.title', 'Jalur Keamanan Siber')
    );
});
```
**Priority**: High
**Existing**: ❌ Not covered

#### TC-BD-008: Search by description returns matching results
```php
it('search by description returns matching results', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->create([
        'title' => 'Path A',
        'description' => 'Belajar tentang kriptografi dan enkripsi data.',
    ]);
    LearningPath::factory()->published()->create([
        'title' => 'Path B',
        'description' => 'Belajar tentang web development.',
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', ['search' => 'kriptografi']));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 1)
        ->where('learningPaths.data.0.title', 'Path A')
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-BD-009: Search is case-insensitive
```php
it('search is case-insensitive', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->create([
        'title' => 'Jalur KEAMANAN Siber',
    ]);

    // Search with lowercase
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', ['search' => 'keamanan']));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 1)
    );

    // Search with uppercase
    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', ['search' => 'KEAMANAN']));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 1)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-BD-010: Empty search returns all published paths
```php
it('empty search returns all published paths', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->count(5)->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', ['search' => '']));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 5)
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

#### TC-BD-011: Search with no results shows empty state
```php
it('search with no results shows empty state', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->count(3)->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'search' => 'tidak ada hasil xyz123'
        ]));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 0)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Filter Functionality')

#### TC-BD-012: Filter by difficulty level
```php
it('filter by difficulty level', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->beginner()->create();
    LearningPath::factory()->published()->intermediate()->count(2)->create();
    LearningPath::factory()->published()->advanced()->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'difficulty' => 'intermediate'
        ]));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 2)
        ->where('filters.difficulty', 'intermediate')
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-BD-013: Combine search and filter
```php
it('combine search and filter', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->published()->beginner()->create([
        'title' => 'Keamanan Dasar',
    ]);
    LearningPath::factory()->published()->advanced()->create([
        'title' => 'Keamanan Lanjutan',
    ]);
    LearningPath::factory()->published()->beginner()->create([
        'title' => 'Web Development',
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'search' => 'Keamanan',
            'difficulty' => 'beginner',
        ]));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 1)
        ->where('learningPaths.data.0.title', 'Keamanan Dasar')
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

### describe('Pagination')

#### TC-BD-014: Results are paginated correctly
```php
it('results are paginated correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    // Create 15 paths (more than default page size of 12)
    for ($i = 1; $i <= 15; $i++) {
        LearningPath::create([
            'title' => "Path $i",
            'slug' => "path-$i",
            'description' => "Description $i",
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $learner->id,
            'updated_by' => $learner->id,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 12)  // First page
        ->where('learningPaths.total', 15)
        ->where('learningPaths.per_page', 12)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-BD-015: Can navigate to second page
```php
it('can navigate to second page', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    for ($i = 1; $i <= 15; $i++) {
        LearningPath::create([
            'title' => "Path $i",
            'slug' => "path-$i",
            'description' => "Description $i",
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $learner->id,
            'updated_by' => $learner->id,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', ['page' => 2]));

    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 3)  // Remaining 3 items
        ->where('learningPaths.current_page', 2)
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

#### TC-BD-016: Pagination preserves search/filter params
```php
it('pagination preserves search and filter params', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    for ($i = 1; $i <= 15; $i++) {
        LearningPath::create([
            'title' => "Keamanan Path $i",
            'slug' => "keamanan-path-$i",
            'description' => "Description $i",
            'difficulty_level' => 'beginner',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $learner->id,
            'updated_by' => $learner->id,
        ]);
    }

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse', [
            'search' => 'Keamanan',
            'difficulty' => 'beginner',
            'page' => 2,
        ]));

    $response->assertInertia(fn ($page) => $page
        ->where('filters.search', 'Keamanan')
        ->where('filters.difficulty', 'beginner')
        ->where('learningPaths.current_page', 2)
    );
});
```
**Priority**: Low
**Existing**: ❌ Not covered

---

### describe('Empty States')

#### TC-BD-017: Empty state when no learning paths exist
```php
it('shows empty state when no learning paths exist', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 0)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

#### TC-BD-018: Empty state when all paths are unpublished
```php
it('shows empty state when all paths are unpublished', function () {
    $learner = User::factory()->create(['role' => 'learner']);

    LearningPath::factory()->unpublished()->count(5)->create();

    $response = $this->actingAs($learner)
        ->get(route('learner.learning-paths.browse'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('learningPaths.data', 0)
    );
});
```
**Priority**: Medium
**Existing**: ❌ Not covered

---

## Test Summary

| Category | Test Count | Existing | New |
|----------|------------|----------|-----|
| Browse Page Access | 3 | 1 | 2 |
| Learning Path Listing | 3 | 1 | 2 |
| Search Functionality | 5 | 0 | 5 |
| Filter Functionality | 2 | 0 | 2 |
| Pagination | 3 | 0 | 3 |
| Empty States | 2 | 0 | 2 |
| **Total** | **18** | **2** | **16** |

---

## Edge Cases to Consider

1. **Special characters in search**: `search=C++`, `search=%_wildcard`
2. **Very long search terms**: 500+ character search string
3. **SQL injection attempts**: `search='; DROP TABLE learning_paths;--`
4. **Unicode search**: `search=データサイエンス` (Japanese characters)
5. **Path with no courses**: Should still display (but show 0 courses)

---

## Implementation Notes

- Use factory states: `->published()`, `->beginner()`, `->intermediate()`, `->advanced()`
- For pagination tests, create paths manually to avoid factory unique constraint issues
- Assert `filters` prop is passed back for UI state restoration
- Test file: `tests/Feature/Journey/LearningPath/LearnerBrowseDiscoveryTest.php`
