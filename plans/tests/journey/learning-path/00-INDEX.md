# Learning Path Journey Test Plan - Index

## Overview

This document serves as the master index for comprehensive end-to-end journey tests covering the **Learning Path** feature in Enteraksi LMS. These tests are designed from the **learner's perspective**, simulating real-world user stories and ensuring the application behaves correctly across all scenarios.

---

## Feature Summary

**Learning Path** allows learners to follow a structured sequence of courses toward a learning goal. Key characteristics:

- **Hierarchical Structure**: Learning Path → Courses → Lessons
- **Prerequisite Modes**: Sequential, Immediate Previous, None
- **Cross-Domain Sync**: Path enrollment ↔ Course enrollment ↔ Lesson progress
- **State Machines**: `PathEnrollmentState` (active/completed/dropped) and `CourseProgressState` (locked/available/in_progress/completed)
- **Progress Tracking**: Based on required courses only (optional courses don't affect completion)

---

## Test Plan Documents

| # | Document | Description | Priority | Test Count | New Tests |
|---|----------|-------------|----------|------------|-----------|
| 01 | [01-learner-browse-discovery.md](./01-learner-browse-discovery.md) | Browse, search, filter learning paths | High | 18 | 16 |
| 02 | [02-learner-enrollment.md](./02-learner-enrollment.md) | Enrollment flows and validation | Critical | 24 | 19 |
| 03 | [03-learner-progress-completion.md](./03-learner-progress-completion.md) | Progress tracking and path completion | Critical | 27 | 24 |
| 04 | [04-prerequisite-modes.md](./04-prerequisite-modes.md) | All three prerequisite modes | Critical | 24 | 23 |
| 05 | [05-cross-domain-sync.md](./05-cross-domain-sync.md) | Course ↔ Path synchronization | Critical | 19 | 15 |
| 06 | [06-re-enrollment.md](./06-re-enrollment.md) | Re-enrollment after dropping | High | 16 | 12 |
| 07 | [07-edge-cases.md](./07-edge-cases.md) | Boundary conditions and error handling | High | 27 | 27 |

**Total Test Cases: 155** (Existing Coverage: ~19, New Tests: ~136)

---

## Existing Test Coverage

Before implementing new tests, review existing coverage:

### Feature Tests (HTTP/Controller Level)
| File | Coverage | Notes |
|------|----------|-------|
| `tests/Feature/LearningPathCrudTest.php` | CRUD + Authorization | Admin/CM operations, not learner journey |
| `tests/Feature/LearningPath/LearningPathEnrollmentTest.php` | Basic enrollment | Endpoint tests, not E2E journey |

### Unit Tests (Domain/Service Level)
| File | Coverage | Notes |
|------|----------|-------|
| `tests/Unit/Domain/LearningPath/Services/PathEnrollmentServiceTest.php` | Enrollment service | Comprehensive unit tests |
| `tests/Unit/Domain/LearningPath/Services/PathProgressServiceTest.php` | Progress service | Unlock, completion logic |
| `tests/Unit/Policies/LearningPathPolicyTest.php` | Policy authorization | Role-based access |

### Gap Analysis
| Area | Existing | Gap |
|------|----------|-----|
| Browse/Discovery | ❌ None | Need tests for search, filter, pagination |
| Complete E2E Journey | ❌ None | Need full browse→enroll→learn→complete flow |
| Prerequisite Mode Variations | ⚠️ Partial | Only sequential tested, need immediate_previous and none |
| Cross-domain Sync E2E | ⚠️ Partial | Unit tested, no E2E integration test |
| Re-enrollment E2E | ⚠️ Partial | Service tested, no HTTP-level journey |
| Edge Cases (path changes) | ❌ None | Path unpublished, course removed mid-progress |

---

## Implementation Priority

### Phase 1: Critical Paths (Week 1)
1. **02-learner-enrollment.md** - Core enrollment flow
2. **03-learner-progress-completion.md** - Progress and completion
3. **04-prerequisite-modes.md** - All three modes

### Phase 2: Integration (Week 2)
4. **05-cross-domain-sync.md** - Course ↔ Path sync
5. **01-learner-browse-discovery.md** - Browse/search

### Phase 3: Edge Cases (Week 3)
6. **06-re-enrollment.md** - Re-enrollment scenarios
7. **07-edge-cases.md** - Boundary conditions

---

## Test File Structure

Recommended new test file structure:

```
tests/Feature/Journey/LearningPath/
├── LearnerBrowseDiscoveryTest.php          # 01
├── LearnerEnrollmentJourneyTest.php        # 02
├── LearnerProgressCompletionTest.php       # 03
├── PrerequisiteModesTest.php               # 04
├── CrossDomainSyncTest.php                 # 05
├── ReEnrollmentJourneyTest.php             # 06
└── EdgeCasesTest.php                       # 07
```

---

## Key Domain Concepts

### State Machines

**PathEnrollmentState**
```
active ─────────┬───────────► completed
                │
                └───────────► dropped
```

**CourseProgressState**
```
locked ──────► available ──────► in_progress ──────► completed
                  │
                  └────────────────────────────────► completed
```

### Prerequisite Modes

| Mode | Description | Unlock Behavior |
|------|-------------|-----------------|
| `sequential` | All previous courses must be completed | Course N unlocks only after 1..N-1 complete |
| `immediate_previous` | Only the course directly before | Course N unlocks when N-1 completes |
| `none` | No prerequisites | All courses available immediately |

### Cross-Domain Relationships

```
LearningPathEnrollment (active)
    └── LearningPathCourseProgress[]
            ├── course_id
            ├── course_enrollment_id  ──────► Enrollment (active)
            ├── state                              └── LessonProgress[]
            └── position
```

---

## Test Helpers

Common test patterns to use:

```php
// Create a learning path with courses
function createPathWithCourses(
    int $courseCount = 3,
    string $prerequisiteMode = 'sequential',
    bool $allRequired = true
): LearningPath {
    $path = LearningPath::factory()->published()->create([
        'prerequisite_mode' => $prerequisiteMode,
    ]);

    $courses = Course::factory()
        ->published()
        ->count($courseCount)
        ->create();

    foreach ($courses as $index => $course) {
        $path->courses()->attach($course->id, [
            'position' => $index + 1,
            'is_required' => $allRequired,
        ]);
    }

    return $path;
}

// Simulate course completion
function completeCourse(Enrollment $enrollment): void {
    $enrollment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'progress_percentage' => 100,
    ]);

    EnrollmentCompleted::dispatch($enrollment);
}

// Assert path enrollment state
function assertPathState(
    LearningPathEnrollment $enrollment,
    string $expectedState,
    int $expectedProgress
): void {
    $enrollment->refresh();
    expect($enrollment->state->getValue())->toBe($expectedState);
    expect($enrollment->progress_percentage)->toBe($expectedProgress);
}
```

---

## User Stories Reference

Each test plan maps to real user stories. Here are the primary personas:

### Rina (Learner - New Employee)
> "Sebagai karyawan baru, saya ingin melihat jalur pembelajaran yang tersedia dan mendaftar untuk mengikuti pelatihan wajib."

### Budi (Learner - Returning User)
> "Sebagai peserta yang sudah terdaftar, saya ingin melanjutkan progress saya dan menyelesaikan semua kursus dalam learning path."

### Dewi (Learner - Re-enrolling)
> "Sebagai peserta yang sebelumnya keluar, saya ingin mendaftar ulang dan memulai dari awal (atau melanjutkan progress lama)."

---

## Running Tests

```bash
# Run all learning path journey tests
php artisan test tests/Feature/Journey/LearningPath/

# Run specific test file
php artisan test tests/Feature/Journey/LearningPath/LearnerEnrollmentJourneyTest.php

# Run with filter
php artisan test --filter="learner completes sequential path"
```

---

## Verification Checklist

After implementing tests, verify:

- [ ] All 7 test plan documents reviewed
- [ ] Test files created in `tests/Feature/Journey/LearningPath/`
- [ ] All tests pass with `php artisan test`
- [ ] No duplicate coverage with existing tests
- [ ] Edge cases documented and tested
- [ ] Indonesian user context maintained (names, messages)

---

## Notes

- Tests should use Pest PHP syntax (consistent with existing tests)
- Use factory states: `->published()`, `->active()`, `->completed()`, `->dropped()`
- Fake events where needed: `Event::fake([EventClass::class])`
- All assertion messages should be in English (for debugging)
- User-facing data (names, messages) should be in Bahasa Indonesia
