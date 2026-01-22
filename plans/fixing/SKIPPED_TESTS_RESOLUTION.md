# Skipped Tests Resolution Tracker

> **Status**: 12/12 tests resolved ✅
> **Last Updated**: 2026-01-21
> **Priority**: Completed

---

## Summary

| Category | Tests | Complexity | Status |
|----------|-------|------------|--------|
| Assessment Grading | 7 | High | ✅ Completed |
| Learning Path Visibility | 1 | Low | ✅ Completed |
| Prerequisite Mode: None | 3 | Medium | ✅ Completed |
| Re-enrollment Preserve Progress | 1 | Low | ✅ Completed |

---

## Category 1: Assessment Grading (7 tests)

**Skip Reason**: `Grade method not implemented in AssessmentController`

**Implementation Required**:
- [ ] Create `grade` method in `AssessmentController`
- [ ] Create grading page/view (Inertia)
- [ ] Add route for grading (`POST /assessments/{attempt}/grade`)
- [ ] Implement `AssessmentPolicy@grade` authorization
- [ ] Create `GradeAttemptRequest` FormRequest
- [ ] Create grading service/logic in Domain layer

**Files to Create/Modify**:
- `app/Http/Controllers/AssessmentController.php` - Add `grade()` method
- `app/Http/Requests/Assessment/GradeAttemptRequest.php` - New
- `app/Policies/AssessmentAttemptPolicy.php` - Add `grade()` method
- `resources/js/pages/assessments/Grade.vue` - New (if needed)
- `routes/assessments.php` or `routes/web.php` - Add route

### Tests to Unskip

- [ ] **1.1** `AdminCapabilitiesTest` → admin can grade any assessment attempt
  - File: `tests/Feature/Authorization/AdminCapabilitiesTest.php:291`
  - Validates: Admin role can access grading for any attempt

- [ ] **1.2** `ResourceIsolationTest` → CM cannot grade another CM's attempts
  - File: `tests/Feature/Authorization/ResourceIsolationTest.php`
  - Validates: Content managers isolated to their own course attempts

- [ ] **1.3** `StatusBasedRestrictionsTest` → cannot grade in-progress attempt
  - File: `tests/Feature/Authorization/StatusBasedRestrictionsTest.php`
  - Validates: Only submitted/completed attempts can be graded

- [ ] **1.4** `CrossRoleCollaborationTest` → admin can grade any attempt
  - File: `tests/Feature/Journey/CrossRoleCollaborationTest.php:763`
  - Validates: Admin grading workflow

- [ ] **1.5** `CrossRoleCollaborationTest` → CM can grade their own attempts
  - File: `tests/Feature/Journey/CrossRoleCollaborationTest.php:796`
  - Validates: CM can grade attempts on their courses

- [ ] **1.6** `CrossRoleCollaborationTest` → CM cannot grade other CM's attempts
  - File: `tests/Feature/Journey/CrossRoleCollaborationTest.php:824`
  - Validates: CM isolation for grading

- [ ] **1.7** `CrossRoleCollaborationTest` → learner cannot access grade page
  - File: `tests/Feature/Journey/CrossRoleCollaborationTest.php:851`
  - Validates: Learners blocked from grading

---

## Category 2: Learning Path Visibility (1 test)

**Skip Reason**: `Controller needs policy authorization for published check`

**Implementation Required**:
- [ ] Add `view` method to `LearningPathPolicy` (or update existing)
- [ ] Update controller to use policy authorization
- [ ] Ensure unpublished paths return 403 for learners

**Files to Modify**:
- `app/Policies/LearningPathPolicy.php` - Add/update `view()` method
- `app/Http/Controllers/LearningPathController.php` - Add `$this->authorize('view', $learningPath)`

### Tests to Unskip

- [ ] **2.1** `LearnerEnrollmentJourneyTest` → cannot view unpublished learning path
  - File: `tests/Feature/Journey/LearningPath/LearnerEnrollmentJourneyTest.php:97`
  - Validates: Learners cannot see draft/unpublished learning paths

---

## Category 3: Prerequisite Mode: None (3 tests)

**Skip Reason**: `PathEnrollmentService::initializeCourseProgress does not yet handle prerequisite_mode=none`

**Implementation Required**:
- [ ] Update `PathEnrollmentService::initializeCourseProgress()` to handle `prerequisite_mode=none`
- [ ] When mode is `none`, create all course enrollments immediately (not just first course)
- [ ] Ensure progress tracking works for parallel course completion

**Files to Modify**:
- `app/Domain/LearningPath/Services/PathEnrollmentService.php` - Update `initializeCourseProgress()`

**Logic Change**:
```php
// Current: Only enrolls in first course
// Needed: If prerequisite_mode === 'none', enroll in ALL courses immediately
```

### Tests to Unskip

- [ ] **3.1** `PrerequisiteModesTest` → all courses available immediately
  - File: `tests/Feature/Journey/LearningPath/PrerequisiteModesTest.php:369`
  - Validates: No waiting for prerequisites when mode is `none`

- [ ] **3.2** `PrerequisiteModesTest` → can complete courses in any order
  - File: `tests/Feature/Journey/LearningPath/PrerequisiteModesTest.php:414`
  - Validates: Non-linear completion allowed

- [ ] **3.3** `PrerequisiteModesTest` → enrollments created for all courses
  - File: `tests/Feature/Journey/LearningPath/PrerequisiteModesTest.php:446`
  - Validates: All CourseEnrollment records exist upfront

---

## Category 4: Re-enrollment Preserve Progress (1 test)

**Skip Reason**: `Controller needs to accept preserve_progress parameter`

**Implementation Required**:
- [ ] Update controller to accept `preserve_progress` boolean parameter
- [ ] Pass parameter to service layer
- [ ] Update FormRequest validation (if exists)

**Files to Modify**:
- `app/Http/Controllers/LearningPathEnrollmentController.php` - Accept `preserve_progress` param
- `app/Http/Requests/LearningPath/ReEnrollRequest.php` - Add validation (if exists)

### Tests to Unskip

- [ ] **4.1** `ReEnrollmentJourneyTest` → user can choose to preserve progress via UI
  - File: `tests/Feature/Journey/LearningPath/ReEnrollmentJourneyTest.php:364`
  - Validates: HTTP endpoint accepts preserve_progress flag

---

## Recommended Resolution Order

1. **Category 2** (Learning Path Visibility) - Quick win, 1 test, simple policy fix
2. **Category 4** (Re-enrollment) - Quick win, 1 test, simple parameter addition
3. **Category 3** (Prerequisite Mode) - Medium effort, 3 tests, service logic update
4. **Category 1** (Assessment Grading) - Largest effort, 7 tests, new feature

---

## Progress Log

| Date | Category | Action | Tests Fixed |
|------|----------|--------|-------------|
| 2026-01-21 | Category 2 | Added `$this->authorize('view', $learningPath)` to LearningPathEnrollmentController::show() | 1 |
| 2026-01-21 | Category 4 | Added `preserve_progress` parameter to enroll controller method | 1 |
| 2026-01-21 | Category 3 | Updated PathEnrollmentService::initializeCourseProgress() to handle `prerequisite_mode=none` | 3 |
| 2026-01-21 | Category 1 | Added `grade()` and `submitGrade()` methods to AssessmentController, updated policy with status check | 7 |

---

## Notes

- Run specific test file after each fix: `php artisan test tests/Feature/Path/To/Test.php`
- Remove `->skip()` call only after implementation is complete
- Update this tracker as progress is made
