# 05 - Edge Cases & Data Integrity Test Plan

## Overview

This document covers boundary conditions, edge cases, data integrity tests, and concurrent operation scenarios.

---

## 1. Numeric Boundary Conditions

### 1.1 Progress Calculations

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_progress_with_zero_lessons` | Empty course | progress = 0 | EXISTS in `EdgeCasesAndBusinessRulesTest` |
| `test_progress_with_one_lesson` | Single lesson | 100% on complete | EXISTS |
| `test_progress_calculation_rounds_correctly` | 3/7 lessons | 42.86% (not infinite) | EXISTS |
| `test_progress_caps_at_100_percent` | All lessons done | progress = 100, not 100.01 | EXISTS |
| `test_progress_with_many_lessons` | 100+ lessons | Correct calculation | **NEEDS TEST** |
| `test_progress_recalculation_idempotent` | Recalculate twice | Same result | EXISTS |

### 1.2 Assessment Scoring

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_score_with_zero_max_score` | No points | percentage = 0, no div/0 | EXISTS |
| `test_score_at_passing_boundary` | Exactly 70 | passed = true | EXISTS |
| `test_score_just_below_boundary` | 69 | passed = false | EXISTS |
| `test_percentage_rounds_to_2_decimals` | 33.333... | 33.33 | EXISTS |
| `test_perfect_score_is_100` | All correct | percentage = 100 | EXISTS |
| `test_all_wrong_is_0` | All incorrect | percentage = 0 | EXISTS |
| `test_partial_score_calculation` | 5/7 questions | ~71.43% | **NEEDS TEST** |

### 1.3 Attempt Limits

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_max_attempts_boundary_exactly_at_limit` | 3/3 attempts | Cannot start 4th | EXISTS |
| `test_max_attempts_zero_means_unlimited` | max=0 | Attempt 11 allowed | EXISTS |
| `test_in_progress_doesnt_count_toward_limit` | 2 submitted + 1 in_progress | Can start new | EXISTS |
| `test_high_max_attempts_value` | max=1000 | Works correctly | **NEEDS TEST** |

### 1.4 Time Values

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_time_spent_accumulates_correctly` | 30s + 45s | 75s total | EXISTS |
| `test_time_spent_with_zero_value` | 0 seconds | Accepted | **NEEDS TEST** |
| `test_time_spent_with_large_value` | 86400 seconds (24h) | Accepted | **NEEDS TEST** |
| `test_media_position_capped_at_duration` | position > duration | Capped | EXISTS |
| `test_media_position_negative_rejected` | position = -1 | 422 Validation | EXISTS |

### 1.5 Rating Values

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_rating_minimum_is_1` | rating = 0 | 422 Validation | EXISTS |
| `test_rating_maximum_is_5` | rating = 6 | 422 Validation | EXISTS |
| `test_rating_must_be_integer` | rating = 3.5 | 422 Validation | EXISTS |
| `test_average_rating_calculation` | Multiple ratings | Correct average | EXISTS |
| `test_average_with_single_rating` | One rating | Same as rating | **NEEDS TEST** |

---

## 2. State Machine Transitions

### 2.1 Course Status Transitions

| From | To | Valid | Test Status |
|------|-----|-------|-------------|
| draft | published | Yes | EXISTS |
| draft | archived | No | EXISTS |
| published | draft | Yes (unpublish) | EXISTS |
| published | archived | Yes | EXISTS |
| archived | draft | No | EXISTS |
| archived | published | No | EXISTS |

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_cannot_archive_draft_course` | Direct archive | EXISTS |
| `test_cannot_republish_archived_course` | Archive → published | EXISTS |
| `test_cannot_unarchive_to_draft` | Archive → draft | EXISTS |

### 2.2 Enrollment Status Transitions

| From | To | Valid | Test Status |
|------|-----|-------|-------------|
| active | completed | Yes | EXISTS |
| active | dropped | Yes | EXISTS |
| completed | dropped | No | EXISTS |
| dropped | active | ? | **NEEDS TEST** |

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_cannot_drop_completed_enrollment` | Drop completed | EXISTS |
| `test_can_re_enroll_after_drop` | Re-enrollment | **NEEDS TEST** |

### 2.3 Assessment Attempt Transitions

| From | To | Valid | Test Status |
|------|-----|-------|-------------|
| in_progress | submitted | Yes | EXISTS |
| submitted | graded | Yes | EXISTS |
| submitted | in_progress | No | EXISTS |
| graded | submitted | No | EXISTS |

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_cannot_resubmit_submitted_attempt` | Resubmit | EXISTS |
| `test_cannot_modify_graded_attempt` | Modify graded | **NEEDS TEST** |

---

## 3. Empty and Null Handling

### 3.1 Empty Collections

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_with_no_sections` | Empty course | Valid, 0% progress | **NEEDS TEST** |
| `test_section_with_no_lessons` | Empty section | Valid | **NEEDS TEST** |
| `test_assessment_with_no_questions` | Empty assessment | Can start, 0 score | EXISTS |
| `test_enrollment_with_no_progress_records` | No LessonProgress | 0% progress | **NEEDS TEST** |
| `test_course_with_no_enrollments` | No students | Valid | **NEEDS TEST** |
| `test_course_with_no_ratings` | No ratings | null average | **NEEDS TEST** |

### 3.2 Null Values

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_with_null_category` | No category | Valid | EXISTS |
| `test_assessment_with_null_time_limit` | No time limit | Valid, no expiry | EXISTS |
| `test_enrollment_with_null_started_at` | Never started | Valid | EXISTS |
| `test_enrollment_with_null_last_lesson_id` | No last lesson | Valid | **NEEDS TEST** |
| `test_invitation_with_null_expires_at` | No expiry | Never expires | **NEEDS TEST** |
| `test_invitation_with_null_message` | No message | Valid | **NEEDS TEST** |

### 3.3 Empty Strings

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_title_cannot_be_empty` | title = "" | 422 Validation | **NEEDS TEST** |
| `test_lesson_title_cannot_be_empty` | title = "" | 422 Validation | **NEEDS TEST** |
| `test_question_text_cannot_be_empty` | text = "" | 422 Validation | EXISTS |
| `test_rating_review_can_be_empty` | review = "" | Valid | EXISTS |

---

## 4. Data Integrity

### 4.1 Soft Delete Behavior

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_deleted_course_preserves_enrollments` | Delete course | Enrollments remain | EXISTS |
| `test_deleted_course_preserves_assessments` | Delete course | Assessments remain | **NEEDS TEST** |
| `test_deleted_assessment_preserves_attempts` | Delete assessment | Attempts remain | EXISTS |
| `test_soft_deleted_course_hidden_from_lists` | Query courses | Not in results | **NEEDS TEST** |

### 4.2 Cascade Behavior

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_delete_section_cascades_to_lessons` | DELETE section | Lessons deleted | **NEEDS TEST** |
| `test_delete_assessment_cascades_to_questions` | DELETE assessment | Questions deleted | **NEEDS TEST** |
| `test_delete_question_cascades_to_options` | DELETE question | Options deleted | **NEEDS TEST** |
| `test_lesson_deletion_preserves_progress` | DELETE lesson | Progress records remain | **NEEDS TEST** |

### 4.3 Timestamp Integrity

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_enrolled_at_set_on_enrollment` | Create enrollment | enrolled_at = now | EXISTS |
| `test_started_at_set_on_first_progress` | First lesson view | started_at = now | **NEEDS TEST** |
| `test_completed_at_set_on_completion` | Complete course | completed_at = now | EXISTS |
| `test_published_at_set_on_publish` | Publish course | published_at = now | EXISTS |
| `test_submitted_at_set_on_submit` | Submit attempt | submitted_at = now | EXISTS |
| `test_graded_at_set_on_grade` | Grade attempt | graded_at = now | EXISTS |

### 4.4 Relationship Integrity

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_enrollment_references_valid_user` | FK constraint | Enforced | **NEEDS TEST** |
| `test_enrollment_references_valid_course` | FK constraint | Enforced | **NEEDS TEST** |
| `test_lesson_progress_references_valid_enrollment` | FK constraint | Enforced | **NEEDS TEST** |
| `test_attempt_references_valid_assessment` | FK constraint | Enforced | **NEEDS TEST** |

---

## 5. Concurrent Operations

### 5.1 Enrollment Concurrency

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_concurrent_enrollment_prevented` | Double enroll | Only one created | **NEEDS TEST** |
| `test_multiple_learners_enroll_same_course` | Parallel enrolls | All succeed | EXISTS |
| `test_progress_isolation_under_concurrency` | Parallel progress | Independent | EXISTS |

### 5.2 Assessment Concurrency

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_concurrent_attempt_start` | Double start | One succeeds | **NEEDS TEST** |
| `test_concurrent_grading` | Double grade | One succeeds | **NEEDS TEST** |
| `test_multiple_learners_same_assessment` | Parallel attempts | All independent | **NEEDS TEST** |

### 5.3 Progress Concurrency

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_rapid_progress_updates` | Fast updates | All recorded | **NEEDS TEST** |
| `test_concurrent_lesson_completions` | Parallel completes | Correct progress | **NEEDS TEST** |

---

## 6. Large Data Handling

### 6.1 Performance with Large Datasets

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_with_100_lessons` | Large course | Works correctly | **NEEDS TEST** |
| `test_assessment_with_100_questions` | Large assessment | Works correctly | **NEEDS TEST** |
| `test_course_with_1000_enrollments` | Many students | Works correctly | **NEEDS TEST** |
| `test_learner_with_100_enrollments` | Many courses | Dashboard loads | **NEEDS TEST** |

### 6.2 String Length Limits

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_title_max_length` | 255 chars | Accepted | **NEEDS TEST** |
| `test_course_title_exceeds_max` | 256 chars | 422 Validation | **NEEDS TEST** |
| `test_review_max_length` | 1000 chars | Accepted | EXISTS |
| `test_review_exceeds_max` | 1001 chars | 422 Validation | EXISTS |

---

## 7. Special Characters and Unicode

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_title_with_unicode` | Indonesian chars | Accepted | **NEEDS TEST** |
| `test_course_title_with_emoji` | Emoji | Accepted or rejected | **NEEDS TEST** |
| `test_search_with_special_characters` | `%_[]` | Safe query | **NEEDS TEST** |
| `test_slug_generation_with_unicode` | Indonesian title | Valid slug | **NEEDS TEST** |

---

## 8. Implementation Examples

### 8.1 Boundary Condition Tests

```php
// tests/Feature/EdgeCases/NumericBoundaryTest.php
<?php

use App\Models\{User, Course, Assessment, Question, AssessmentAttempt};

describe('Numeric Boundary Conditions', function () {

    describe('Progress Calculations', function () {

        it('handles course with many lessons', function () {
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Create 100 lessons
            $lessons = Lesson::factory()->count(100)->create([
                'course_section_id' => $section->id,
            ]);

            ['user' => $learner, 'enrollment' => $enrollment] = createEnrolledLearner($course);

            // Complete 37 lessons
            foreach ($lessons->take(37) as $lesson) {
                LessonProgress::factory()->create([
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                    'is_completed' => true,
                ]);
            }

            $enrollment->refresh();
            progressService()->recalculateProgress($enrollment);

            expect($enrollment->progress_percentage)->toBe(37); // 37%
        });

        it('progress never exceeds 100', function () {
            $course = createPublishedCourseWithContent(1, 3);
            ['user' => $learner, 'enrollment' => $enrollment] = createEnrolledLearner($course);

            // Complete all lessons
            foreach ($course->lessons as $lesson) {
                LessonProgress::factory()->create([
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                    'is_completed' => true,
                ]);
            }

            // Recalculate multiple times
            progressService()->recalculateProgress($enrollment);
            progressService()->recalculateProgress($enrollment);

            expect($enrollment->progress_percentage)->toBe(100);
        });

    });

    describe('Assessment Scoring', function () {

        it('handles passing score at exact boundary', function () {
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            // Create questions worth 10 points total
            Question::factory()->count(10)->create([
                'assessment_id' => $assessment->id,
                'points' => 1,
            ]);

            $attempt = AssessmentAttempt::factory()->create([
                'assessment_id' => $assessment->id,
                'score' => 7,
                'max_score' => 10,
            ]);

            $attempt->calculateScore();

            expect($attempt->percentage)->toBe(70.0);
            expect($attempt->passed)->toBeTrue();
        });

        it('handles just below passing boundary', function () {
            $assessment = Assessment::factory()->published()->create([
                'passing_score' => 70,
            ]);

            $attempt = AssessmentAttempt::factory()->create([
                'assessment_id' => $assessment->id,
                'score' => 69,
                'max_score' => 100,
            ]);

            $attempt->calculateScore();

            expect($attempt->percentage)->toBe(69.0);
            expect($attempt->passed)->toBeFalse();
        });

    });

});
```

### 8.2 State Machine Tests

```php
// tests/Feature/EdgeCases/StateMachineTest.php
<?php

use App\Models\{Course, Enrollment};

describe('State Machine Transitions', function () {

    describe('Enrollment States', function () {

        it('cannot transition from completed to dropped', function () {
            $course = createPublishedCourseWithContent();
            ['enrollment' => $enrollment] = createEnrolledLearner($course);

            // Complete the enrollment
            $enrollment->update(['status' => 'completed']);

            // Try to drop
            $this->actingAs($enrollment->user)
                ->delete(route('courses.unenroll', $course))
                ->assertForbidden();

            expect($enrollment->refresh()->status)->toBe('completed');
        });

        it('handles re-enrollment after drop', function () {
            $course = createPublishedCourseWithContent();
            ['user' => $learner, 'enrollment' => $enrollment] = createEnrolledLearner($course);

            // Drop
            $enrollment->update(['status' => 'dropped']);

            // Try to re-enroll
            $response = $this->actingAs($learner)
                ->post(route('courses.enroll', $course));

            // Document expected behavior (either new enrollment or reactivate)
            // This test documents the behavior for future reference
        });

    });

});
```

---

## 9. Test Priority

### Priority 1 (Critical - Data Integrity)
1. Soft delete and cascade behavior
2. Timestamp integrity
3. Concurrent enrollment prevention
4. Progress calculation edge cases

### Priority 2 (High - Boundaries)
5. State machine invalid transitions
6. Numeric boundary conditions
7. Empty/null handling

### Priority 3 (Medium - Robustness)
8. Large data handling
9. Unicode and special characters
10. Concurrent operations

---

## 10. Key Files Reference

- `tests/Feature/EdgeCasesAndBusinessRulesTest.php` - Existing edge case tests
- `app/Models/Enrollment.php` - Enrollment state machine
- `app/Models/Course.php` - Course state machine
- `app/Domain/Progress/` - Progress calculation services
- `database/migrations/` - FK constraints and schema
