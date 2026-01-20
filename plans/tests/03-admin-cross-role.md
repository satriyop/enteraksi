# 03 - Admin & Cross-Role Integration Test Plan

## Overview

This document covers LMS Admin unique capabilities and cross-role interaction testing, ensuring proper authorization and collaboration workflows.

---

## 1. LMS Admin Unique Capabilities

### 1.1 Course Publishing (Admin Only)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_publish_course` | POST publish | Course published | EXISTS in `CoursePublishingStateMachineTest` |
| `test_admin_can_unpublish_course` | POST unpublish | Course draft | EXISTS |
| `test_admin_can_archive_course` | POST archive | Course archived | EXISTS |
| `test_admin_can_set_course_status` | PATCH status | Status updated | EXISTS |
| `test_admin_can_set_course_visibility` | PATCH visibility | Visibility updated | EXISTS |
| `test_admin_publish_sets_published_at` | After publish | Timestamp set | EXISTS |
| `test_admin_publish_sets_published_by` | After publish | User ID set | EXISTS |

### 1.2 Course Management (Any Course)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_view_all_courses` | GET courses | All courses returned | **NEEDS TEST** |
| `test_admin_can_edit_published_course` | PATCH published | Updated | EXISTS |
| `test_admin_can_edit_others_course` | PATCH other's | Updated | EXISTS |
| `test_admin_can_delete_any_course` | DELETE any | Soft deleted | EXISTS |
| `test_admin_can_restore_soft_deleted` | POST restore | Restored | EXISTS in `CoursePolicyTest` |
| `test_admin_can_force_delete` | DELETE force | Permanently deleted | EXISTS in `CoursePolicyTest` |

### 1.3 Content Management (Published Courses)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_add_lessons_to_published_course` | POST lesson | Created | **NEEDS TEST** |
| `test_admin_can_add_sections_to_published_course` | POST section | Created | **NEEDS TEST** |
| `test_admin_can_reorder_sections_in_published` | POST reorder | Reordered | **NEEDS TEST** |
| `test_admin_can_modify_content_in_any_state` | PATCH any | Updated | **NEEDS TEST** |

### 1.4 Assessment Publishing (Admin Only)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_publish_assessment` | POST publish | Published | EXISTS in `AssessmentCrudTest` |
| `test_admin_can_unpublish_assessment` | POST unpublish | Draft | EXISTS |
| `test_admin_can_archive_assessment` | POST archive | Archived | EXISTS |
| `test_admin_can_publish_others_assessment` | Admin publish | Published | **NEEDS TEST** |

### 1.5 System-Wide Access

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_view_all_enrollments` | GET enrollments | All returned | **NEEDS TEST** |
| `test_admin_can_view_all_assessment_attempts` | GET attempts | All returned | **NEEDS TEST** |
| `test_admin_can_grade_any_attempt` | POST grade | Graded | EXISTS |
| `test_admin_can_override_grading` | Re-grade | Updated | **NEEDS TEST** |
| `test_admin_sees_system_metrics_on_dashboard` | GET dashboard | Metrics shown | **NEEDS TEST** |

### 1.6 Bulk Operations

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_bulk_publish_courses` | POST bulk | Multiple published | **NEEDS TEST** |
| `test_admin_can_bulk_archive_courses` | POST bulk | Multiple archived | **NEEDS TEST** |

---

## 2. Cross-Role Collaboration Workflows

### 2.1 Content Creation to Learner Consumption

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_cm_creates_admin_publishes_learner_enrolls` | Full workflow | **NEEDS TEST** |
| `test_full_course_lifecycle` | Create → Publish → Enroll → Learn → Complete | **NEEDS TEST** |
| `test_cm_creates_assessment_admin_publishes_learner_attempts` | Assessment workflow | **NEEDS TEST** |

**Workflow: Content Manager Creates, Admin Publishes, Learner Consumes**

```
1. Content Manager creates course (draft)
2. Content Manager adds sections and lessons
3. Content Manager creates assessment with questions
4. LMS Admin reviews and publishes course
5. LMS Admin publishes assessment
6. Learner discovers and enrolls
7. Learner completes lessons
8. Learner takes and passes assessment
9. Enrollment marked complete
```

### 2.2 Assessment Grading Chain

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_learner_submits_cm_grades_admin_reviews` | Grading chain | **NEEDS TEST** |
| `test_cm_grades_own_assessment_learner_views_result` | View result | **NEEDS TEST** |
| `test_admin_can_regrade_after_cm_grading` | Re-grade | **NEEDS TEST** |

**Workflow: Essay Grading**

```
1. Learner submits attempt with essay answers
2. Content Manager grades essay questions
3. System calculates total score
4. LMS Admin reviews and can override
5. Learner views final result
```

### 2.3 Course Revision Workflow

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_admin_unpublishes_cm_edits_admin_republishes` | Revision cycle | **NEEDS TEST** |
| `test_enrolled_learner_continues_after_revision` | After republish | **NEEDS TEST** |
| `test_progress_preserved_through_revision` | Progress kept | **NEEDS TEST** |

**Workflow: Course Revision**

```
1. Published course has enrolled learners
2. Admin unpublishes for revision
3. Content Manager makes edits
4. Admin reviews and republishes
5. Existing learners continue (progress preserved)
```

### 2.4 Multi-User Course Interaction

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_multiple_cms_different_courses_isolation` | Separate courses | **NEEDS TEST** |
| `test_multiple_learners_same_course_independent` | Independent progress | EXISTS in `EdgeCasesAndBusinessRulesTest` |
| `test_learner_multiple_courses_by_different_managers` | Multi-course | **NEEDS TEST** |

### 2.5 Invitation-Based Access

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_cm_invites_learner_accepts_enrolls` | Invitation workflow | EXISTS partially |
| `test_trainer_invites_to_others_course` | Cross-course invite | EXISTS |
| `test_invitation_expiry_prevents_enrollment` | Expired invite | **NEEDS TEST** |

---

## 3. Role-Based View Differences

### 3.1 Dashboard Differences

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_admin_dashboard_shows_system_metrics` | Admin view | **NEEDS TEST** |
| `test_cm_dashboard_shows_only_own_courses` | CM view | **NEEDS TEST** |
| `test_learner_dashboard_shows_enrolled_courses` | Learner view | EXISTS in `LearnerDashboardTest` |
| `test_admin_sees_all_pending_grading` | Admin grading queue | **NEEDS TEST** |
| `test_cm_sees_only_own_pending_grading` | CM grading queue | **NEEDS TEST** |

### 3.2 Course Index Views

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_admin_course_index_shows_all_with_filters` | Admin list | **NEEDS TEST** |
| `test_cm_course_index_shows_own_plus_published` | CM list | **NEEDS TEST** |
| `test_learner_browse_shows_only_published_public` | Learner browse | **NEEDS TEST** |
| `test_learner_cannot_see_draft_courses` | Hidden drafts | **NEEDS TEST** |

### 3.3 Course Detail Views

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_admin_sees_full_management_controls` | Admin controls | **NEEDS TEST** |
| `test_cm_sees_limited_controls_on_own` | CM controls | **NEEDS TEST** |
| `test_cm_sees_no_controls_on_others` | No controls | **NEEDS TEST** |
| `test_learner_sees_enrollment_button` | Learner view | **NEEDS TEST** |
| `test_enrolled_learner_sees_lesson_access` | Enrolled view | **NEEDS TEST** |

### 3.4 Assessment Views

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_admin_sees_all_attempts_in_detail` | Admin view | **NEEDS TEST** |
| `test_cm_sees_attempts_for_own_assessments` | CM view | **NEEDS TEST** |
| `test_learner_sees_only_own_attempts` | Learner view | **NEEDS TEST** |

---

## 4. Data Isolation Tests

### 4.1 User Data Isolation

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_enrollment_data_isolated_between_users` | Separate enrollments | **NEEDS TEST** |
| `test_lesson_progress_isolated_between_enrollments` | Separate progress | **NEEDS TEST** |
| `test_assessment_attempt_isolated_between_users` | Separate attempts | **NEEDS TEST** |
| `test_attempt_answers_isolated_between_attempts` | Separate answers | **NEEDS TEST** |
| `test_course_ratings_isolated_to_enrolled_learner` | Separate ratings | **NEEDS TEST** |

### 4.2 Course Data Isolation

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_course_sections_belong_to_single_course` | Section isolation | **NEEDS TEST** |
| `test_lessons_belong_to_single_section` | Lesson isolation | **NEEDS TEST** |
| `test_assessments_belong_to_single_course` | Assessment isolation | **NEEDS TEST** |
| `test_questions_belong_to_single_assessment` | Question isolation | **NEEDS TEST** |

### 4.3 Multi-Tenant Scenarios

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_cm_a_cannot_access_cm_b_statistics` | Stats isolation | **NEEDS TEST** |
| `test_learner_progress_course_a_independent_of_b` | Cross-course | **NEEDS TEST** |
| `test_assessment_attempts_isolated_across_courses` | Cross-course | **NEEDS TEST** |

---

## 5. Authorization Consistency Tests

### 5.1 Policy vs Controller Consistency

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_controller_matches_policy_course_update` | Consistency check | **NEEDS TEST** |
| `test_controller_matches_policy_course_delete` | Consistency check | **NEEDS TEST** |
| `test_controller_matches_policy_assessment_publish` | Consistency check | **NEEDS TEST** |
| `test_controller_matches_policy_attempt_grade` | Consistency check | **NEEDS TEST** |

### 5.2 API vs Web Route Consistency

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_api_same_auth_as_web_for_courses` | API consistency | **NEEDS TEST** |
| `test_api_same_auth_as_web_for_assessments` | API consistency | **NEEDS TEST** |
| `test_api_same_auth_as_web_for_attempts` | API consistency | **NEEDS TEST** |

---

## 6. Implementation Examples

### 6.1 Cross-Role Workflow Test

```php
// tests/Feature/Journey/CrossRoleCollaborationTest.php
<?php

use App\Models\{User, Course, Enrollment, Assessment, Lesson};

describe('Cross-Role Collaboration', function () {

    it('content manager creates, admin publishes, learner enrolls and completes', function () {
        // Setup users
        $cm = User::factory()->create(['role' => 'content_manager']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);

        // Step 1: CM creates course
        $this->actingAs($cm)
            ->post(route('courses.store'), [
                'title' => 'Test Course',
                'short_description' => 'Description',
            ])
            ->assertRedirect();

        $course = Course::first();
        expect($course->status)->toBe('draft');

        // Step 2: CM adds section and lesson
        $this->actingAs($cm)
            ->post(route('courses.sections.store', $course), [
                'title' => 'Section 1',
            ]);

        $section = $course->sections()->first();

        $this->actingAs($cm)
            ->post(route('sections.lessons.store', $section), [
                'title' => 'Lesson 1',
                'content_type' => 'text',
            ]);

        // Step 3: Admin publishes
        $this->actingAs($admin)
            ->post(route('courses.publish', $course))
            ->assertRedirect();

        $course->refresh();
        expect($course->status)->toBe('published');

        // Step 4: Learner enrolls
        $this->actingAs($learner)
            ->post(route('courses.enroll', $course))
            ->assertRedirect();

        $enrollment = Enrollment::first();
        expect($enrollment->status)->toBe('active');

        // Step 5: Learner completes lesson
        $lesson = $course->lessons->first();
        $this->actingAs($learner)
            ->patch(route('lessons.progress.update', [$course, $lesson]), [
                'current_page' => 1,
                'total_pages' => 1,
            ]);

        // Step 6: Verify completion
        $enrollment->refresh();
        expect($enrollment->status)->toBe('completed');
    });

});
```

### 6.2 Data Isolation Test

```php
// tests/Feature/Authorization/DataIsolationTest.php
<?php

use App\Models\{User, Course, Enrollment, LessonProgress};

describe('Data Isolation', function () {

    it('learner progress isolated between users', function () {
        $course = createPublishedCourseWithContent();
        $lesson = $course->lessons->first();

        // Create two learners
        $learner1 = User::factory()->create(['role' => 'learner']);
        $learner2 = User::factory()->create(['role' => 'learner']);

        // Both enroll
        $enrollment1 = Enrollment::factory()->create([
            'user_id' => $learner1->id,
            'course_id' => $course->id,
        ]);
        $enrollment2 = Enrollment::factory()->create([
            'user_id' => $learner2->id,
            'course_id' => $course->id,
        ]);

        // Learner 1 makes progress
        $this->actingAs($learner1)
            ->patch(route('lessons.progress.update', [$course, $lesson]), [
                'current_page' => 5,
                'total_pages' => 10,
            ]);

        // Verify learner 1 has progress
        expect(LessonProgress::where('enrollment_id', $enrollment1->id)->count())->toBe(1);

        // Verify learner 2 has NO progress
        expect(LessonProgress::where('enrollment_id', $enrollment2->id)->count())->toBe(0);

        // Learner 2 cannot see learner 1's progress
        $this->actingAs($learner2)
            ->get(route('courses.lessons.show', [$course, $lesson]))
            ->assertSuccessful()
            ->assertInertia(fn ($page) =>
                $page->where('progress', null)
            );
    });

});
```

---

## 7. Test Priority Matrix

### Priority 1 (Critical - Security)
1. Admin unique capability tests
2. Cross-role authorization boundaries
3. Data isolation tests
4. API/Web consistency

### Priority 2 (High - Workflow)
5. End-to-end cross-role workflows
6. Course revision workflow
7. Grading chain workflow

### Priority 3 (Medium - Views)
8. Role-based dashboard differences
9. Course index view differences
10. Assessment view differences

---

## 8. Gaps Identified

### Missing Policy Tests
- `AssessmentAttemptPolicyTest` does not exist as a dedicated file
- Authorization consistency tests between controllers and policies

### Inconsistencies Found
- **LearningPathPolicy**: Content managers CAN publish their own learning paths (different from courses/assessments)
- **AssessmentPolicy::grade()**: Trainers may not be able to grade (only checks `isContentManager`)

### Recommended Actions
1. Create `AssessmentAttemptPolicyTest.php`
2. Verify trainer grading intentionally blocked
3. Document LearningPath publish exception

---

## 9. Key Files Reference

- `app/Policies/CoursePolicy.php` - Course authorization (admin-only methods)
- `app/Policies/AssessmentPolicy.php` - Assessment authorization
- `app/Policies/AssessmentAttemptPolicy.php` - Attempt authorization
- `app/Models/User.php` - Role checking methods
- `tests/Unit/Policies/CoursePolicyTest.php` - Existing policy tests
- `tests/Feature/CoursePublishingStateMachineTest.php` - State machine tests
