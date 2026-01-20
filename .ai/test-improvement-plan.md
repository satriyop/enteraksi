# Test Improvement Plan: Preparing for Major Refactor

## Executive Summary

This plan outlines a phased approach to strengthening the test suite before implementing SOLID principles, Strategy patterns, DI patterns, State Machine patterns, and Event-Driven architecture.

**Current State**: 25 test files (~4,046 lines), strong authentication coverage, gaps in core business flows.

**Goal**: Create a comprehensive safety net that will catch regressions during refactoring.

---

## Philosophy: Multi-Perspective Testing

Each test should consider multiple stakeholders:

| Perspective | Focus Areas |
|-------------|-------------|
| **Learner** | Can I access content? Is my progress saved? Can I complete assessments? |
| **Content Manager** | Can I create/edit courses? Are my drafts safe? |
| **LMS Admin** | Can I publish/archive? Can I manage all users? |
| **Trainer** | Can I invite learners? Can I see their progress? |
| **Future Developer** | Is the behavior documented through tests? Are edge cases covered? |
| **Security Auditor** | Are authorization rules enforced? Is data isolated? |

---

## Phase 1: Assessment Workflow Tests (CRITICAL)

**Priority**: 🔴 Highest
**Risk**: Assessment code has ZERO feature tests for learner experience
**Estimated Tests**: 8 test files, ~60 test methods

### 1.1 AssessmentAttemptFlowTest
Tests the complete learner journey through an assessment.

```
Scenarios:
- Learner can start an attempt on published assessment
- Learner cannot start attempt on draft/archived assessment
- Learner cannot start attempt without enrollment
- Attempt creates correct initial state (in_progress, attempt_number=1)
- Multiple attempts increment attempt_number correctly
- Cannot exceed max_attempts limit
```

### 1.2 AssessmentAnswerTest
Tests question answering across all question types.

```
Scenarios per question type (multiple_choice, true_false, matching, short_answer, essay, file_upload):
- Can submit answer
- Answer validation rules enforced
- Answer persisted correctly
- Can update answer before submission
- Cannot answer after submission
```

### 1.3 AssessmentTimeLimitTest
Tests time-based assessment rules.

```
Scenarios:
- Time limit enforced (cannot submit after expiry)
- Time limit grace period (if any)
- No time limit assessments work correctly
- Time remaining calculation accurate
- Auto-submit on time expiry (if implemented)
```

### 1.4 AssessmentSubmissionTest
Tests the submission process.

```
Scenarios:
- Can submit completed attempt
- Cannot submit already submitted attempt
- Cannot submit other user's attempt
- Partial submission (unanswered questions)
- Submission timestamp recorded correctly
- Status changes to 'submitted'
```

### 1.5 AssessmentAutoGradingTest
Tests automatic scoring for objective questions.

```
Scenarios:
- Multiple choice: correct answer scores full points
- Multiple choice: incorrect answer scores zero
- Multiple choice: partial credit (if enabled)
- True/false grading accuracy
- Matching question grading
- Short answer exact match grading
- Score calculation with mixed question types
- Percentage calculation accuracy
- Pass/fail determination based on passing_score
```

### 1.6 AssessmentManualGradingTest
Tests manual grading workflow for subjective questions.

```
Scenarios:
- Essay questions marked as requiring manual grading
- File upload questions marked as requiring manual grading
- Grader can assign scores to manual questions
- Grader can provide feedback
- Total score recalculates after manual grading
- Status changes from 'submitted' to 'graded' to 'completed'
- Only authorized users can grade (admin, content manager, owner)
```

### 1.7 AssessmentReviewTest
Tests learner's ability to review attempts.

```
Scenarios:
- Can review submitted attempt (if allow_review=true)
- Cannot review if allow_review=false
- Review shows correct answers (if show_correct_answers=true)
- Review hides correct answers (if show_correct_answers=false)
- Cannot modify answers during review
```

### 1.8 AssessmentEdgeCasesTest
Tests boundary conditions and unusual scenarios.

```
Scenarios:
- Attempt on assessment with no questions
- Attempt on assessment with 100+ questions (performance)
- Concurrent attempts from same user (prevented?)
- Assessment deleted during attempt
- Question deleted during attempt
- Score calculation with zero-point questions
- Division by zero prevention in percentage calculation
```

---

## Phase 2: Enrollment Lifecycle Tests (HIGH PRIORITY)

**Priority**: 🟠 High
**Risk**: Enrollment state transitions affect learner access
**Estimated Tests**: 5 test files, ~45 test methods

### 2.1 EnrollmentCreationTest
Tests enrollment creation scenarios.

```
Scenarios:
- Learner can self-enroll in public published course
- Learner cannot self-enroll in restricted course
- Learner cannot self-enroll in draft course
- Learner cannot self-enroll in archived course
- Cannot enroll if already enrolled
- Enrollment creates correct initial state
- enrolled_at timestamp set correctly
```

### 2.2 EnrollmentInvitationFlowTest
Tests invitation-based enrollment.

```
Scenarios:
- Invited learner can accept and enroll in restricted course
- Cannot accept expired invitation
- Cannot accept already accepted invitation
- Cannot accept invitation for another user
- Decline invitation marks status correctly
- Bulk invitation creates correct records
- Duplicate invitation prevented
```

### 2.3 EnrollmentProgressTest
Tests progress calculation accuracy.

```
Scenarios:
- Progress percentage updates on lesson completion
- Progress calculation with 1 lesson
- Progress calculation with 10 lessons
- Progress calculation with 50 lessons (performance)
- Progress caps at 100%
- Progress never negative
- started_at set on first lesson access
- last_lesson_id tracks correctly
```

### 2.4 EnrollmentCompletionTest
Tests auto-completion behavior.

```
Scenarios:
- Enrollment auto-completes when all lessons done
- completed_at timestamp set on completion
- Status changes to 'completed'
- Cannot complete enrollment without completing all lessons
- Re-completion on lesson addition (should reset?)
```

### 2.5 EnrollmentAuthorizationTest
Tests enrollment-based access control.

```
Scenarios:
- Enrolled learner can access course lessons
- Non-enrolled learner cannot access restricted course
- Non-enrolled learner CAN view public course info
- Dropped enrollment revokes access
- Completed enrollment retains access
- Content manager access doesn't require enrollment
```

---

## Phase 3: Course Publishing State Machine Tests

**Priority**: 🟡 Medium
**Risk**: State transitions affect content visibility
**Estimated Tests**: 4 test files, ~35 test methods

### 3.1 CourseStateTransitionTest
Tests valid state transitions.

```
State Machine:
  draft ──→ published ──→ archived
    ↑          │
    └──────────┘ (unpublish)

Scenarios:
- Draft can be published
- Published can be archived
- Published can be unpublished (back to draft)
- Archived can be unpublished (back to draft)
- Draft cannot be archived directly
- Cannot transition to same state
```

### 3.2 CoursePublishValidationTest
Tests publishing prerequisites.

```
Scenarios:
- Cannot publish without at least one section
- Cannot publish without at least one lesson
- Can publish with minimal content (1 section, 1 lesson)
- Publish validation error messages clear
- Unpublish has no prerequisites
```

### 3.3 CourseVisibilityTest
Tests visibility changes and impacts.

```
Scenarios:
- Public course visible to all authenticated users
- Restricted course visible only to enrolled/invited
- Visibility change doesn't affect existing enrollments
- Draft courses not visible to learners regardless of visibility
- Archived courses not visible in catalog
```

### 3.4 CoursePublishAuthorizationTest
Tests who can change course states.

```
Scenarios:
- LMS Admin can publish any course
- Content Manager CANNOT publish (create/edit only)
- Course owner who is admin CAN publish own course
- Trainer cannot publish
- Learner cannot publish
```

---

## Phase 4: Policy Unit Tests (Refactoring Safety Net)

**Priority**: 🟡 Medium
**Risk**: Authorization bugs can expose sensitive data
**Estimated Tests**: 7 test files, ~100 test methods

These tests directly test Policy classes without going through HTTP layer.

### 4.1 CoursePolicyTest
```
Methods to test (11 total):
- viewAny, view, create, update, delete
- forceDelete, restore
- publish, unpublish, archive
- enroll
```

### 4.2 AssessmentPolicyTest
```
Methods to test:
- viewAny, view, create, update, delete
- publish, unpublish, archive
- attempt, grade
```

### 4.3 LessonPolicyTest
```
Methods to test:
- viewAny, view, create, update, delete
- Preview access
```

### 4.4 EnrollmentPolicyTest (needs creation)
```
Methods to test:
- create (enroll)
- delete (unenroll)
- update (progress tracking)
```

### 4.5 LessonProgressPolicyTest (needs creation)
```
Methods to test:
- update (own progress only)
- view (own or admin)
```

### 4.6 CourseInvitationPolicyTest
```
Methods to test:
- viewAny, view, create, delete
- accept, decline (invitee only)
```

### 4.7 LearningPathPolicyTest
```
Methods to test:
- viewAny, view, create, update, delete
- publish, unpublish
```

---

## Phase 5: Edge Cases & Business Rules

**Priority**: 🟢 Important
**Risk**: Subtle bugs in edge cases
**Estimated Tests**: 6 test files, ~50 test methods

### 5.1 LessonContentTypeTest
```
For each content type (text, video, youtube, audio, document, conference):
- Content renders correctly
- Progress tracking appropriate for type
- Media duration extraction (video/audio)
- YouTube ID extraction
- Rich content HTML generation
```

### 5.2 ProgressTrackingEdgeCasesTest
```
Scenarios:
- Progress on deleted lesson
- Progress update race condition
- Progress after course modification
- Progress recalculation accuracy
- Media progress at exactly 90% (auto-complete boundary)
```

### 5.3 DataIntegrityTest
```
Scenarios:
- Soft delete cascade behavior
- Restore cascade behavior
- Foreign key constraints enforced
- Orphaned records prevented
- Unique constraints enforced
```

### 5.4 ConcurrencyTest
```
Scenarios:
- Two browsers updating same progress
- Rapid progress updates
- Bulk operations atomicity
```

### 5.5 PerformanceRegressionTest
```
Scenarios:
- Course with 50 lessons loads efficiently
- Assessment with 100 questions loads efficiently
- User with 20 enrollments dashboard loads efficiently
- Progress recalculation with many lessons efficient
```

### 5.6 SecurityBoundaryTest
```
Scenarios:
- User cannot access other user's progress
- User cannot grade other user's assessment
- User cannot enroll on behalf of others
- User cannot accept others' invitations
- CSRF protection on all mutations
```

---

## Implementation Order

```
Week 1: Phase 1 (Assessment) - CRITICAL
  └─ Day 1-2: AssessmentAttemptFlowTest, AssessmentAnswerTest
  └─ Day 3-4: AssessmentAutoGradingTest, AssessmentManualGradingTest
  └─ Day 5: AssessmentTimeLimitTest, AssessmentSubmissionTest, Edge cases

Week 2: Phase 2 (Enrollment)
  └─ Day 1-2: EnrollmentCreationTest, EnrollmentInvitationFlowTest
  └─ Day 3-4: EnrollmentProgressTest, EnrollmentCompletionTest
  └─ Day 5: EnrollmentAuthorizationTest

Week 3: Phase 3 & 4 (Publishing + Policies)
  └─ Day 1-2: Course state machine tests
  └─ Day 3-5: Policy unit tests

Week 4: Phase 5 (Edge Cases)
  └─ Day 1-2: Content types, Progress edge cases
  └─ Day 3-4: Data integrity, Concurrency
  └─ Day 5: Performance, Security boundaries
```

---

## Test Naming Conventions

Follow existing project patterns:
```php
/** @test */
public function test_<subject>_<scenario>_<expected_outcome>()

// Examples:
public function test_learner_can_start_assessment_attempt()
public function test_attempt_fails_when_max_attempts_exceeded()
public function test_auto_grading_calculates_correct_score()
public function test_enrollment_auto_completes_when_all_lessons_done()
```

---

## Test Data Strategy

Use existing factories with states:
```php
// Already available
Course::factory()->draft()
Course::factory()->published()
Assessment::factory()->published()->withQuestions(10)
Enrollment::factory()->active()
Enrollment::factory()->completed()

// Need to create
AssessmentAttempt::factory()->inProgress()
AssessmentAttempt::factory()->submitted()
AssessmentAttempt::factory()->graded()
```

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| Test files | 25 | 55+ |
| Test methods | ~150 | 400+ |
| Lines of test code | ~4,046 | 12,000+ |
| Assessment flow coverage | 0% | 100% |
| Enrollment lifecycle coverage | ~30% | 100% |
| Policy unit test coverage | 0% | 100% |

---

## Next Steps

1. ✅ Review and approve this plan
2. 🔲 Start Phase 1: Assessment workflow tests
3. 🔲 Create missing factory states
4. 🔲 Implement tests phase by phase
5. 🔲 Validate all tests pass before refactoring begins

---

*Document created: 2026-01-20*
*Author: Claude (Opus 4.5)*
*Purpose: Pre-refactor test safety net*
