# 01 - Learner Journey Integration Test Plan

## Overview

This document covers the complete learner experience in the Enteraksi LMS, from course discovery through completion and rating.

---

## 1. Course Discovery and Browsing

### 1.1 Public Course Browsing

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_guest_can_view_published_public_courses` | Browse /courses without auth | List of published public courses | **NEEDS TEST** |
| `test_guest_cannot_view_draft_courses` | Browse courses | Draft courses excluded | **NEEDS TEST** |
| `test_guest_cannot_view_archived_courses` | Browse courses | Archived courses excluded | **NEEDS TEST** |
| `test_guest_cannot_view_restricted_courses` | Browse courses | Restricted courses hidden | **NEEDS TEST** |
| `test_learner_sees_enrollment_status_on_courses` | Auth browse | Shows "enrolled" badge | **NEEDS TEST** |
| `test_course_listing_shows_metadata` | Browse courses | Title, duration, rating, lesson count | **NEEDS TEST** |
| `test_course_search_by_title` | Search query | Matching courses returned | **NEEDS TEST** |
| `test_course_filter_by_category` | Filter | Filtered results | **NEEDS TEST** |
| `test_course_filter_by_difficulty` | Filter by level | Filtered results | **NEEDS TEST** |
| `test_course_pagination` | Many courses | Paginated results | **NEEDS TEST** |

### 1.2 Course Detail View

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_guest_can_view_public_course_details` | View /courses/{id} | Course info, sections, ratings | Partial in `CourseRatingTest` |
| `test_guest_cannot_view_restricted_course_details` | View restricted | 403 Forbidden | **NEEDS TEST** |
| `test_enrolled_learner_sees_progress_info` | View enrolled course | Progress percentage shown | **NEEDS TEST** |
| `test_non_enrolled_sees_enroll_button` | View public course | "Enroll" CTA visible | **NEEDS TEST** |
| `test_enrolled_learner_sees_continue_button` | View enrolled course | "Continue Learning" CTA | **NEEDS TEST** |
| `test_course_outline_shows_sections_lessons` | View course | Organized structure | **NEEDS TEST** |
| `test_ratings_displayed_correctly` | View with ratings | Average, count, reviews | EXISTS in `CourseRatingTest` |

---

## 2. Enrollment Flow

### 2.1 Self-Enrollment (Public Courses)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_can_self_enroll_in_public_course` | POST /courses/{id}/enroll | Active enrollment created | EXISTS in `EnrollmentLifecycleTest` |
| `test_enrollment_has_correct_initial_state` | After enrollment | status=active, progress=0 | EXISTS |
| `test_guest_cannot_enroll` | POST without auth | Redirect to login | EXISTS |
| `test_cannot_enroll_in_draft_course` | Enroll in draft | 403 Forbidden | EXISTS |
| `test_cannot_enroll_in_archived_course` | Enroll in archived | 403 Forbidden | EXISTS |
| `test_cannot_self_enroll_in_restricted_course` | Enroll without invite | 403 Forbidden | EXISTS |
| `test_cannot_double_enroll` | Enroll twice | Fails, keeps single | EXISTS |
| `test_enrollment_sets_started_at_on_first_lesson` | View first lesson | started_at timestamp set | **NEEDS TEST** |

### 2.2 Invitation-Based Enrollment

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_sees_pending_invitations_on_dashboard` | View dashboard | invitedCourses list | EXISTS in `InvitedCoursesTest` |
| `test_learner_can_accept_invitation` | POST /invitations/{id}/accept | Enrollment created | EXISTS |
| `test_learner_can_decline_invitation` | POST /invitations/{id}/decline | Status updated, no enrollment | EXISTS |
| `test_cannot_accept_others_invitation` | Accept other's | 403 Forbidden | EXISTS |
| `test_expired_invitations_not_shown` | Dashboard with expired | Empty list | EXISTS |
| `test_accepted_invitations_not_shown` | Dashboard with accepted | Empty list | EXISTS |
| `test_learner_can_view_restricted_course_after_invite` | View restricted | 200 OK | **NEEDS TEST** |
| `test_accepting_invitation_when_already_enrolled` | Accept when enrolled | Handle gracefully | **NEEDS TEST** |

### 2.3 Dropping/Unenrolling

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_can_drop_active_enrollment` | DELETE /courses/{id}/unenroll | Status = 'dropped' | EXISTS |
| `test_cannot_drop_completed_enrollment` | Drop completed | Error, stays completed | EXISTS |
| `test_guest_cannot_drop` | DELETE without auth | Redirect to login | EXISTS |
| `test_cannot_drop_others_enrollment` | Drop other's | 404 Not Found | EXISTS |
| `test_dropped_enrollment_preserves_progress_data` | Drop enrollment | LessonProgress remains | **NEEDS TEST** |
| `test_can_re_enroll_after_dropping` | Enroll after drop | New enrollment or reactivate | **NEEDS TEST** |

---

## 3. Lesson Viewing and Progress

### 3.1 Lesson Access

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_guest_cannot_view_lessons` | GET without auth | Redirect to login | EXISTS in `LessonViewTest` |
| `test_non_enrolled_cannot_view_lessons` | GET without enrollment | 403 Forbidden | EXISTS |
| `test_enrolled_learner_can_view_lessons` | GET with enrollment | 200 OK, content shown | EXISTS |
| `test_course_owner_can_view_any_lesson` | Owner views | 200 OK | EXISTS |
| `test_lms_admin_can_view_any_lesson` | Admin views | 200 OK | EXISTS |
| `test_lesson_must_belong_to_course` | Wrong course URL | 404 Not Found | EXISTS |
| `test_dropped_enrollment_cannot_view_lessons` | Dropped views | 403 Forbidden | EXISTS |
| `test_completed_enrollment_can_view_lessons` | Completed views | 200 OK | **NEEDS TEST** |

### 3.2 Navigation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_lesson_view_returns_navigation_data` | View middle lesson | prevLesson, nextLesson | EXISTS |
| `test_first_lesson_has_no_previous` | View first | prevLesson is null | EXISTS |
| `test_last_lesson_has_no_next` | View last | nextLesson is null | EXISTS |
| `test_navigation_respects_section_order` | Cross-section | Correct across sections | **NEEDS TEST** |

### 3.3 Page-Based Progress (Text Content)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_enrolled_user_can_update_progress` | PATCH progress | LessonProgress updated | EXISTS in `LessonProgressTest` |
| `test_progress_creates_record_on_first_update` | First call | New record created | EXISTS |
| `test_highest_page_reached_only_increases` | Navigate back | highest unchanged | EXISTS |
| `test_lesson_completes_at_last_page` | Reach total_pages | is_completed = true | EXISTS |
| `test_course_progress_updates_on_completion` | Complete lesson | Enrollment % updated | EXISTS |
| `test_progress_validates_page_numbers` | current_page = 0 | 422 Validation | EXISTS |
| `test_progress_validates_total_pages` | total_pages = 0 | 422 Validation | EXISTS |
| `test_current_page_capped_at_total` | current > total | Capped | EXISTS |
| `test_time_spent_accumulates` | Multiple updates | Sums correctly | EXISTS |
| `test_pagination_metadata_stored` | Send metadata | Saved | EXISTS |

### 3.4 Media-Based Progress (Video/Audio)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_can_update_media_progress` | PATCH progress/media | Media fields updated | EXISTS |
| `test_media_auto_completes_at_90_percent` | 90% watched | is_completed = true | EXISTS |
| `test_media_does_not_complete_below_90` | 83% watched | is_completed = false | EXISTS |
| `test_position_validates_non_negative` | position = -1 | 422 Validation | EXISTS |
| `test_duration_validates_positive` | duration = 0 | 422 Validation | EXISTS |
| `test_position_capped_at_duration` | position > duration | Capped | EXISTS |
| `test_guest_cannot_update_media_progress` | PATCH without auth | 401 Unauthorized | EXISTS |
| `test_non_enrolled_cannot_update_media` | PATCH without enrollment | 403 Forbidden | EXISTS |

### 3.5 Progress Edge Cases

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_dropped_cannot_update_progress` | Dropped updates | 403 Forbidden | EXISTS |
| `test_updates_last_lesson_id_on_enrollment` | Progress update | last_lesson_id set | EXISTS |
| `test_progress_with_zero_lessons_course` | Empty course | progress = 0 | EXISTS in `EdgeCasesAndBusinessRulesTest` |
| `test_progress_with_one_lesson_course` | Single lesson | 100% on complete | EXISTS |
| `test_progress_calculation_rounds_correctly` | 3/7 lessons | 42.9% | EXISTS |
| `test_progress_is_idempotent` | Recalculate | Same result | EXISTS |

---

## 4. Assessment Flow

### 4.1 Starting Assessment Attempts

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_enrolled_learner_can_start_attempt` | POST start | in_progress attempt | EXISTS in `AssessmentAttemptFlowTest` |
| `test_attempt_has_correct_initial_state` | After start | status=in_progress, attempt_number=1 | EXISTS |
| `test_guest_cannot_start_attempt` | POST without auth | Redirect to login | EXISTS |
| `test_non_enrolled_cannot_start` | POST without enrollment | 403 Forbidden | EXISTS |
| `test_cannot_start_draft_assessment` | Start draft | 403 Forbidden | EXISTS |
| `test_cannot_start_archived_assessment` | Start archived | 403 Forbidden | EXISTS |
| `test_multiple_attempts_increment_number` | Second attempt | attempt_number = 2 | EXISTS |
| `test_cannot_exceed_max_attempts` | At limit | 403 Forbidden | EXISTS |
| `test_in_progress_dont_count_toward_limit` | Check limit | Only submitted/graded count | EXISTS |
| `test_unlimited_attempts_when_max_is_zero` | max=0, attempt 11 | Allowed | EXISTS |

### 4.2 Viewing Attempts

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_can_view_own_attempt` | GET attempt | 200 OK | EXISTS |
| `test_learner_cannot_view_others_attempt` | GET other's | 403 Forbidden | EXISTS |
| `test_admin_can_view_any_attempt` | Admin GET | 200 OK | EXISTS |
| `test_content_manager_can_view_own_assessment_attempts` | CM GET | 200 OK | EXISTS |

### 4.3 Submitting Answers

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_can_submit_in_progress_attempt` | POST submit | Answers created, status updated | EXISTS in `AssessmentSubmissionTest` |
| `test_submission_creates_answer_records` | Submit | AttemptAnswer records | EXISTS |
| `test_submitted_at_timestamp_recorded` | After submit | submitted_at set | EXISTS |
| `test_answers_array_is_required` | Submit empty | 422 Validation | EXISTS |
| `test_question_id_must_exist` | Invalid ID | 422 Validation | EXISTS |
| `test_partial_submission_allowed` | Some answers | Only submitted saved | EXISTS |
| `test_cannot_submit_others_attempt` | Submit other's | 403 Forbidden | EXISTS |
| `test_cannot_submit_already_submitted` | Submit submitted | 403 Forbidden | EXISTS |
| `test_cannot_submit_graded_attempt` | Submit graded | 403 Forbidden | EXISTS |

### 4.4 Auto-Grading

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_true_false_correct_scores_full_points` | Answer 'true' | is_correct=true, full score | EXISTS in `AssessmentAutoGradingTest` |
| `test_true_false_benar_accepted` | Answer 'benar' | is_correct=true | EXISTS |
| `test_true_false_incorrect_scores_zero` | Wrong answer | score=0 | EXISTS |
| `test_true_false_case_insensitive` | Answer 'TRUE' | Treated as 'true' | EXISTS |
| `test_total_score_calculated_correctly` | Multiple questions | Sum of scores | EXISTS |
| `test_percentage_calculated_correctly` | Score/max | Correct % | EXISTS |
| `test_pass_when_meeting_threshold` | 70% with 70 pass | passed=true | EXISTS |
| `test_fail_below_threshold` | 50% with 70 pass | passed=false | EXISTS |
| `test_status_graded_when_all_auto` | All TF/MC | status=graded | EXISTS |
| `test_status_submitted_when_manual_needed` | Has essay | status=submitted | EXISTS |

### 4.5 Manual Grading

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_essay_requires_manual_grading` | Check question | requiresManualGrading=true | EXISTS in `AssessmentManualGradingTest` |
| `test_file_upload_requires_manual` | Check question | requiresManualGrading=true | EXISTS |
| `test_mc_tf_dont_require_manual` | Check question | requiresManualGrading=false | EXISTS |
| `test_calculate_score_updates_attempt` | Grade then calc | Correct totals | EXISTS |
| `test_mixed_auto_manual_grading` | Both types | Combined score correct | EXISTS |

---

## 5. Course Completion

### 5.1 Auto-Completion

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_enrollment_completes_when_all_lessons_done` | Complete last | status=completed | EXISTS in `EnrollmentLifecycleTest` |
| `test_completed_at_timestamp_set` | On completion | completed_at set | EXISTS |
| `test_does_not_complete_with_partial_progress` | Some lessons | status=active | EXISTS |
| `test_completed_enrollment_stays_completed` | Recalculate | Still completed | EXISTS |
| `test_progress_caps_at_100_percent` | All lessons | progress=100 | EXISTS |

### 5.2 Completion with Assessments (HIGH PRIORITY)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_required_assessments_block_completion` | Incomplete required | status=active | **NEEDS TEST** |
| `test_optional_assessments_dont_block_completion` | Skip optional | Can complete | **NEEDS TEST** |
| `test_passing_required_assessment_allows_completion` | Pass required | Can complete | **NEEDS TEST** |
| `test_failing_required_assessment_blocks` | Fail required | Cannot complete | **NEEDS TEST** |

### 5.3 Post-Completion Access

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_completed_learner_can_still_view_lessons` | Access after | 200 OK | **NEEDS TEST** |
| `test_completed_learner_can_view_assessments` | Access after | 200 OK | **NEEDS TEST** |
| `test_completed_learner_can_retake_assessments` | New attempt | Allowed if max permits | **NEEDS TEST** |

---

## 6. Course Ratings

### 6.1 Creating Ratings

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_enrolled_user_can_create_rating` | POST rating | CourseRating created | EXISTS in `CourseRatingTest` |
| `test_can_rate_without_review` | Rating only | review=null | EXISTS |
| `test_non_enrolled_cannot_rate` | POST without enrollment | 403 Forbidden | EXISTS |
| `test_guest_cannot_rate` | POST without auth | 401 Unauthorized | EXISTS |
| `test_cannot_rate_same_course_twice` | Double rate | 403 Forbidden | EXISTS |
| `test_rating_validates_minimum` | rating=0 | 422 Validation | EXISTS |
| `test_rating_validates_maximum` | rating=6 | 422 Validation | EXISTS |
| `test_rating_validates_integer` | rating=3.5 | 422 Validation | EXISTS |

### 6.2 Updating/Deleting Ratings

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_user_can_update_own_rating` | PATCH rating | Updated | EXISTS |
| `test_user_cannot_update_others_rating` | PATCH other's | 403 Forbidden | EXISTS |
| `test_user_can_delete_own_rating` | DELETE rating | Removed | EXISTS |
| `test_user_cannot_delete_others_rating` | DELETE other's | 403 Forbidden | EXISTS |
| `test_admin_can_delete_any_rating` | Admin DELETE | Removed | EXISTS |

---

## 7. Learner Dashboard

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_shows_enrolled_courses` | Dashboard | myLearning has courses | EXISTS in `LearnerDashboardTest` |
| `test_shows_correct_progress_data` | Dashboard | progress_percentage | EXISTS |
| `test_shows_course_metadata` | Dashboard | title, duration, difficulty | EXISTS |
| `test_orders_by_recent_activity` | Multiple courses | Most recent first | EXISTS |
| `test_empty_when_no_enrollments` | No courses | Empty myLearning | EXISTS |
| `test_shows_completed_courses` | Completed | In myLearning | EXISTS |
| `test_does_not_show_dropped` | Dropped | Not in myLearning | EXISTS |
| `test_shows_featured_courses` | Dashboard | featuredCourses list | EXISTS |
| `test_guest_cannot_access` | GET without auth | Redirect to login | EXISTS |
| `test_non_learner_cannot_access` | Content manager | 403 Forbidden | EXISTS |

---

## 8. End-to-End Journey Tests (CRITICAL - NEW)

### 8.1 Complete Happy Path Journeys

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_full_journey_browse_enroll_learn_complete` | Public course, all lessons, no assessments | **NEEDS TEST** |
| `test_full_journey_invite_accept_learn_complete` | Restricted course via invitation | **NEEDS TEST** |
| `test_full_journey_learn_pass_assessment_complete` | Course with required assessment | **NEEDS TEST** |
| `test_full_journey_complete_rate_view_dashboard` | End-to-end with rating | **NEEDS TEST** |

### 8.2 Multi-User Scenarios

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_multiple_learners_independent_progress` | Same course, different progress | EXISTS in `EdgeCasesAndBusinessRulesTest` |
| `test_progress_isolation_between_enrollments` | User A doesn't affect B | EXISTS |

---

## 9. Implementation Notes

### Suggested Test File Structure

```php
// tests/Feature/Journey/LearnerCompleteJourneyTest.php
<?php

use App\Models\{User, Course, Enrollment, Lesson, Assessment};

describe('Learner Complete Journey', function () {

    it('completes full public course journey', function () {
        // 1. Create published course with content
        $course = createPublishedCourseWithContent(2, 3);

        // 2. Learner enrolls
        $learner = User::factory()->create(['role' => 'learner']);
        $this->actingAs($learner)
            ->post(route('courses.enroll', $course))
            ->assertRedirect();

        // 3. Verify enrollment
        expect($learner->enrollments()->first())
            ->status->toBe('active')
            ->progress_percentage->toBe(0);

        // 4. Complete each lesson
        $lessons = $course->lessons;
        foreach ($lessons as $lesson) {
            $this->patch(route('lessons.progress.update', [$course, $lesson]), [
                'current_page' => 1,
                'total_pages' => 1,
            ]);
        }

        // 5. Verify completion
        $learner->refresh();
        expect($learner->enrollments()->first())
            ->status->toBe('completed')
            ->progress_percentage->toBe(100);
    });

});
```

### Key Files Reference

- `tests/Feature/EnrollmentLifecycleTest.php` - Pattern for enrollment tests
- `tests/Feature/LessonProgressTest.php` - Pattern for progress tests
- `tests/Feature/AssessmentAttemptFlowTest.php` - Pattern for assessment tests
- `app/Policies/CoursePolicy.php` - Authorization rules
- `app/Domain/Progress/Contracts/ProgressTrackingServiceContract.php` - Progress service

---

## 10. Priority Summary

### HIGH Priority
1. End-to-end journey tests (none exist)
2. Required assessment blocking completion
3. Re-enrollment after dropping
4. Completion with assessments tests

### MEDIUM Priority
5. Course browsing tests
6. Post-completion access tests
7. Cross-section navigation

### LOW Priority
8. Search and filtering
9. Pagination
